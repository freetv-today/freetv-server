<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/ProductionContentSnapshotArchiveService.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\ProductionContentSnapshotArchiveException;
use FreeTV\Admin\ProductionContentSnapshotArchiveService;
use FreeTV\Admin\ServerPaths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermanentFailingArchive
{
    public function open(string $path,int $flags):bool{return file_put_contents($path,'partial')!==false;} public function addEmptyDir(string $name):bool{return true;} public function addFile(string $path,string $name):bool{return true;} public function setMtimeName(string $name,int $time):bool{return true;} public function close():bool{return false;}
}

final class ProductionContentSnapshotArchiveServiceTest extends TestCase
{
    private string $root; private string $name='freetv-content-snapshot-20260828T183426Z';
    protected function setUp():void{$this->root=sys_get_temp_dir().'/freetv-permanent-archive-'.bin2hex(random_bytes(8));mkdir($this->root.'/public',0700,true);}
    protected function tearDown():void{DatasetPackage::removeTree($this->root);}

    public function testCreatesDeterministicPrivateArchiveAndResolvesIt():void
    {
        $snapshot=$this->fixture($this->name);file_put_contents($this->root.'/temp/data-snapshots/outside','exclude');$service=$this->service();$result=$service->create($this->name);self::assertSame($this->name,$result['name']);self::assertSame($this->root.'/temp/data-snapshots/'.$this->name.'.zip',$result['path']);self::assertGreaterThan(0,$result['bytes']);self::assertSame(0600,fileperms($result['path'])&0777);
        $zip=new ZipArchive();self::assertTrue($zip->open($result['path'],ZipArchive::RDONLY));$entries=[];for($i=0;$i<$zip->numFiles;$i++)$entries[]=$zip->getNameIndex($i);self::assertSame([$this->name.'/',$this->name.'/manifest.json',$this->name.'/playlists.json',$this->name.'/playlist_shows.json',$this->name.'/thumbs-manifest.json',$this->name.'/thumbs/',$this->name.'/thumbs/tt0000001.jpg',$this->name.'/thumbs/tt0000002.jpg'],$entries);self::assertSame('one',$zip->getFromName($this->name.'/thumbs/tt0000001.jpg'));self::assertFalse($zip->locateName('outside'));$zip->close();self::assertSame($result,$service->resolveArchive($this->name));self::assertDirectoryExists($snapshot);
    }

    public function testArchiveCollisionDoesNotOverwriteBytes():void{$this->fixture($this->name);$service=$this->service();$result=$service->create($this->name);$hash=hash_file('sha256',$result['path']);$this->expectFailure(fn()=>$service->create($this->name),409);self::assertSame($hash,hash_file('sha256',$result['path']));}

    #[DataProvider('invalidNameProvider')]
    public function testInvalidNamesAreRejectedForCreationAndResolution(string $name):void{$this->expectFailure(fn()=>$this->service()->create($name),400);$this->expectFailure(fn()=>$this->service()->resolveArchive($name),400);}
    public static function invalidNameProvider():array{return array_map(static fn($x)=>[$x],['../freetv-content-snapshot-20260828T183426Z','/tmp/freetv-content-snapshot-20260828T183426Z','.staging-test','freetv-content-snapshot-20260828T183426Z/thumbs']);}

    #[DataProvider('corruptionProvider')]
    public function testSnapshotIntegrityFailuresReturnConflict(string $case):void
    {
        $root=$this->fixture($this->name);
        match($case){'extra'=>file_put_contents($root.'/unexpected','x'),'missing'=>unlink($root.'/playlists.json'),'manifest'=>file_put_contents($root.'/manifest.json','{}'),'count'=>$this->mutateJson($root.'/manifest.json',static function(array &$v){$v['counts']['shows']=99;}),'hash'=>$this->mutateJson($root.'/manifest.json',static function(array &$v){$v['files'][0]['sha256']=str_repeat('0',64);}),'bytes'=>$this->mutateJson($root.'/manifest.json',static function(array &$v){$v['files'][0]['bytes']++;}),'thumb extra'=>file_put_contents($root.'/thumbs/bad.png','bad'),'thumb symlink'=>$this->replaceWithLink($root.'/thumbs/tt0000001.jpg')};
        $this->expectFailure(fn()=>$this->service()->create($this->name),409);
    }
    public static function corruptionProvider():array{return array_map(static fn($x)=>[$x],['extra','missing','manifest','count','hash','bytes','thumb extra','thumb symlink']);}

    public function testMissingAndCorruptArchivesAreNotResolved():void
    {
        mkdir($this->root.'/temp/data-snapshots',0700,true);$this->expectFailure(fn()=>$this->service()->resolveArchive($this->name),404);file_put_contents($this->root.'/temp/data-snapshots/'.$this->name.'.zip','not zip');$this->expectFailure(fn()=>$this->service()->resolveArchive($this->name),404);unlink($this->root.'/temp/data-snapshots/'.$this->name.'.zip');$target=$this->root.'/outside';file_put_contents($target,'x');symlink($target,$this->root.'/temp/data-snapshots/'.$this->name.'.zip');$this->expectFailure(fn()=>$this->service()->resolveArchive($this->name),404);
    }

    public function testFinalizationFailureCleansTemporaryAndCompletedArchive():void
    {
        $this->fixture($this->name);$before=$this->snapshotTree($this->root.'/temp/data-snapshots/'.$this->name);$service=new ProductionContentSnapshotArchiveService(new ServerPaths($this->root),static fn()=>new PermanentFailingArchive());$this->expectFailure(fn()=>$service->create($this->name),500);self::assertSame([],glob($this->root.'/temp/data-snapshots/.archive-*.tmp')?:[]);self::assertFileDoesNotExist($this->root.'/temp/data-snapshots/'.$this->name.'.zip');self::assertSame($before,$this->snapshotTree($this->root.'/temp/data-snapshots/'.$this->name));
    }

    private function service():ProductionContentSnapshotArchiveService{return new ProductionContentSnapshotArchiveService(new ServerPaths($this->root));}
    private function fixture(string $name):string{$root=$this->root.'/temp/data-snapshots/'.$name;mkdir($root.'/thumbs',0700,true);$this->writeJson($root.'/playlists.json',[['id'=>1],['id'=>2]]);$this->writeJson($root.'/playlist_shows.json',[['id'=>1]]);file_put_contents($root.'/thumbs/tt0000001.jpg','one');file_put_contents($root.'/thumbs/tt0000002.jpg','two');$this->writeJson($root.'/thumbs-manifest.json',['format_version'=>1,'files'=>[$this->meta($root,'thumbs/tt0000001.jpg'),$this->meta($root,'thumbs/tt0000002.jpg')]]);$stamp=substr($name,-16,-1);$canonical=substr($stamp,0,4).'-'.substr($stamp,4,2).'-'.substr($stamp,6,2).'T'.substr($stamp,9,2).':'.substr($stamp,11,2).':'.substr($stamp,13,2).'.000Z';$this->writeJson($root.'/manifest.json',['format_version'=>1,'production_snapshot_at'=>$canonical,'capture_completed_at'=>$canonical,'counts'=>['playlists'=>2,'shows'=>1,'thumbnails'=>2],'files'=>[$this->meta($root,'playlists.json'),$this->meta($root,'playlist_shows.json'),$this->meta($root,'thumbs-manifest.json')]]);return $root;}
    private function meta(string $root,string $path):array{return ['path'=>$path,'sha256'=>hash_file('sha256',$root.'/'.$path),'bytes'=>filesize($root.'/'.$path)];}
    private function writeJson(string $path,array $v):void{file_put_contents($path,json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");}
    private function mutateJson(string $path,callable $change):bool{$v=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$change($v);$this->writeJson($path,$v);return true;}
    private function replaceWithLink(string $path):bool{$outside=$this->root.'/outside-thumb';file_put_contents($outside,'outside');unlink($path);return symlink($outside,$path);}
    private function expectFailure(callable $fn,int $status):void{try{$fn();self::fail('Expected archive failure');}catch(ProductionContentSnapshotArchiveException $e){self::assertSame($status,$e->getHttpStatus());}}
    private function snapshotTree(string $root):array{$out=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($it as $f)$out[substr($f->getPathname(),strlen($root)+1)]=hash_file('sha256',$f->getPathname());ksort($out);return $out;}
}
