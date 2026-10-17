<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/ProductionContentSnapshotService.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\ProductionContentSnapshotException;
use FreeTV\Admin\ProductionContentSnapshotService;
use FreeTV\Admin\ServerPaths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductionContentSnapshotServiceTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root=sys_get_temp_dir().'/freetv-permanent-snapshot-'.bin2hex(random_bytes(8)); mkdir($this->root.'/public/thumbs',0700,true); }
    protected function tearDown(): void { DatasetPackage::removeTree($this->root); }

    public function testCaptureNormalizesTimesOrdersRowsAndCapturesEligibleThumbnails(): void
    {
        file_put_contents($this->root.'/public/thumbs/tt7654321.jpg','second'); file_put_contents($this->root.'/public/thumbs/tt0000002.jpg','first'); file_put_contents($this->root.'/public/thumbs/readme.txt','ignored'); file_put_contents($this->root.'/public/thumbs/TT0000003.jpg','ignored');
        $playlists=[['id'=>8,'sort_order'=>2,'filename'=>'last.json','created_at'=>null],['id'=>3,'sort_order'=>1,'filename'=>'first.json','created_at'=>'2026-01-01 00:00:00'],['id'=>4,'sort_order'=>1,'filename'=>'second.json','created_at'=>null]];
        $shows=[['id'=>9,'playlist_id'=>8,'sort_order'=>0,'title'=>'Last'],['id'=>7,'playlist_id'=>3,'sort_order'=>2,'title'=>'Second'],['id'=>2,'playlist_id'=>3,'sort_order'=>0,'title'=>'First']];
        $times=[new DateTimeImmutable('2026-08-28T14:01:02.987-04:00'),new DateTimeImmutable('2026-08-28T18:01:04.321Z')];
        $clock=static function()use(&$times):DateTimeImmutable{return array_shift($times);};
        $result=(new ProductionContentSnapshotService(new ServerPaths($this->root),static fn()=>['playlists'=>$playlists,'playlist_shows'=>$shows],$clock))->create();
        self::assertSame('2026-08-28T18:01:02.000Z',$result['production_snapshot_at']); self::assertSame('2026-08-28T18:01:04.000Z',$result['capture_completed_at']); self::assertSame(['playlists'=>3,'shows'=>3,'thumbnails'=>2],$result['counts']); self::assertSame($this->root.'/temp/data-snapshots/freetv-content-snapshot-20260828T180102Z',$result['path']); self::assertStringNotContainsString($this->root.'/public/',$result['path']); self::assertSame(0700,fileperms($result['path'])&0777); self::assertSame(0600,fileperms($result['path'].'/manifest.json')&0777);
        $storedP=$this->json($result['path'].'/playlists.json'); $storedS=$this->json($result['path'].'/playlist_shows.json'); self::assertSame([3,4,8],array_column($storedP,'id')); self::assertSame([2,7,9],array_column($storedS,'id')); self::assertSame('2026-01-01 00:00:00',$storedP[0]['created_at']);
        $thumbs=$this->json($result['path'].'/thumbs-manifest.json'); self::assertSame(['thumbs/tt0000002.jpg','thumbs/tt7654321.jpg'],array_column($thumbs['files'],'path')); self::assertSame('first',file_get_contents($result['path'].'/thumbs/tt0000002.jpg')); foreach($thumbs['files'] as $f){self::assertSame(hash_file('sha256',$result['path'].'/'.$f['path']),$f['sha256']);self::assertSame(filesize($result['path'].'/'.$f['path']),$f['bytes']);}
        $manifest=$this->json($result['path'].'/manifest.json'); self::assertSame(1,$manifest['format_version']); self::assertSame($result['counts'],$manifest['counts']); self::assertSame(['playlists.json','playlist_shows.json','thumbs-manifest.json'],array_column($manifest['files'],'path')); foreach($manifest['files'] as $f){self::assertSame(hash_file('sha256',$result['path'].'/'.$f['path']),$f['sha256']);self::assertSame(filesize($result['path'].'/'.$f['path']),$f['bytes']);}
    }

    #[DataProvider('failureProvider')]
    public function testInjectedFailuresCleanStagingAndPreserveExistingSnapshots(string $case): void
    {
        file_put_contents($this->root.'/public/thumbs/tt0000001.jpg','thumb'); mkdir($this->root.'/temp/data-snapshots/prior',0700,true); file_put_contents($this->root.'/temp/data-snapshots/prior/keep','keep');
        $stager=$case==='copy'?static fn()=>throw new RuntimeException('copy'):null; $hasher=$case==='hash'?static fn()=>false:null; $writer=$case==='write'?static fn()=>throw new RuntimeException('write'):null;
        $times=[new DateTimeImmutable('2026-08-28T19:00:00Z'),new DateTimeImmutable('2026-08-28T19:00:01Z')]; $service=new ProductionContentSnapshotService(new ServerPaths($this->root),static fn()=>['playlists'=>[['id'=>1,'sort_order'=>0]],'playlist_shows'=>[['id'=>1,'playlist_id'=>1,'sort_order'=>0]]],static fn()=>array_shift($times),$stager,$hasher,$writer);
        try{$service->create();self::fail('Failure accepted');}catch(ProductionContentSnapshotException){}
        self::assertSame([],glob($this->root.'/temp/data-snapshots/.staging-*')?:[]); self::assertSame('keep',file_get_contents($this->root.'/temp/data-snapshots/prior/keep')); self::assertSame([],glob($this->root.'/temp/data-snapshots/freetv-content-snapshot-*')?:[]);
    }
    public static function failureProvider(): array{return ['copy'=>['copy'],'hash'=>['hash'],'write'=>['write']];}

    public function testLockContentionAndDuplicateNameDoNotCreateSnapshot(): void
    {
        mkdir($this->root.'/temp/data-snapshots',0700,true);$lock=fopen($this->root.'/temp/data-snapshots/.lock','c');self::assertIsResource($lock);self::assertTrue(flock($lock,LOCK_EX|LOCK_NB));$service=$this->serviceAt('2026-08-28T18:30:00Z');try{$service->create();self::fail('Lock ignored');}catch(ProductionContentSnapshotException){}flock($lock,LOCK_UN);fclose($lock);self::assertSame([],glob($this->root.'/temp/data-snapshots/freetv-content-snapshot-*')?:[]);
        mkdir($this->root.'/temp/data-snapshots/freetv-content-snapshot-20260828T183000Z');try{$service->create();self::fail('Collision accepted');}catch(ProductionContentSnapshotException $e){self::assertStringContainsString('already exists',$e->getMessage());}
    }

    public function testCliContainsWebExecutionGuard():void{self::assertStringContainsString("PHP_SAPI !== 'cli'",(string)file_get_contents(dirname(__DIR__,3).'/scripts/create-content-snapshot.php'));}
    private function serviceAt(string $time):ProductionContentSnapshotService{if(!is_dir($this->root.'/public/thumbs'))mkdir($this->root.'/public/thumbs',0700,true);return new ProductionContentSnapshotService(new ServerPaths($this->root),static fn()=>['playlists'=>[['id'=>1,'sort_order'=>0]],'playlist_shows'=>[['id'=>1,'playlist_id'=>1,'sort_order'=>0]]],static fn()=>new DateTimeImmutable($time));}
    private function json(string $path):array{return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);}
}
