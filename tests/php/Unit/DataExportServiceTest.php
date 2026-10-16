<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/publication/DataExportService.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/../../../public/api/admin/publication/ConfigPublicationSerializer.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\Publication\ConfigPublicationSerializer;
use FreeTV\Admin\Publication\DataExportService;
use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationStatusService;
use FreeTV\Admin\Publication\PublicationUndoService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermanentExportStatus extends PublicationStatusService
{
    public function __construct(private array $result) {}
    public function status():array{return $this->result;}
}

final class DataExportServiceTest extends TestCase
{
    private string $root;private string $public;private array $playlists;private array $shows;private array $settings;
    protected function setUp():void
    {
        $this->root=sys_get_temp_dir().'/freetv-permanent-export-'.bin2hex(random_bytes(8));$this->public=$this->root.'/public';mkdir($this->public.'/playlists',0700,true);mkdir($this->public.'/thumbs',0700,true);
        $this->playlists=[['id'=>2,'filename'=>'two.json','dbtitle'=>'Two','dbversion'=>null,'author'=>null,'email'=>null,'link'=>null,'is_default'=>0,'sort_order'=>1,'lastupdated'=>'2026-01-02 00:00:00'],['id'=>1,'filename'=>'one.json','dbtitle'=>'One','dbversion'=>null,'author'=>'Author','email'=>null,'link'=>null,'is_default'=>1,'sort_order'=>0,'lastupdated'=>'2026-01-01 00:00:00']];
        usort($this->playlists,static fn($a,$b)=>[$a['sort_order'],$a['id']]<=>[$b['sort_order'],$b['id']]);$this->shows=[1=>[['id'=>1,'sort_order'=>0,'category'=>'Drama','status'=>'active','identifier'=>'one','title'=>'One','description'=>null,'start_year'=>null,'end_year'=>null,'imdb'=>null,'group_name'=>null]],2=>[['id'=>2,'sort_order'=>0,'category'=>'Comedy','status'=>'active','identifier'=>'two','title'=>'Two','description'=>null,'start_year'=>null,'end_year'=>null,'imdb'=>null,'group_name'=>null]]];$this->settings=['show_ads'=>false];
        $times=['one.json'=>'2026-08-01T10:00:00.000Z','two.json'=>'2026-08-02T10:00:00.000Z'];$entries=[];foreach($this->playlists as $p){$artifact=PlaylistPublicationSerializer::serialize($p,$this->shows[$p['id']],$times[$p['filename']]);$this->write('playlists/'.$p['filename'],$artifact);$entry=['filename'=>$p['filename'],'dbtitle'=>$p['dbtitle'],'lastupdated'=>$times[$p['filename']]];if($p['author']!==null)$entry['author']=$p['author'];$entries[]=$entry;}$this->write('config.json',ConfigPublicationSerializer::serialize($this->settings,'2026-08-03T10:00:00.000Z'));$this->write('playlists/index.json',['default'=>'one.json','playlists'=>$entries]);file_put_contents($this->public.'/thumbs/tt1.jpg','excluded');
    }
    protected function tearDown():void{DatasetPackage::removeTree($this->root);}

    public function testRealStatusStartsSynchronizedAndExportIsFaithfulAndImmutable():void
    {
        $status=$this->publicationStatus();$value=$status->status();foreach($value['playlists'] as $p)self::assertFalse($p['changed']);self::assertFalse($value['config']['changed']);self::assertFalse($value['default_playlist']['changed']);
        $undo=$this->root.'/temp/publication-undo';mkdir($undo,0700,true);file_put_contents($undo.'/history','preserve');$beforePublic=$this->tree($this->public);$beforeRows=serialize([$this->playlists,$this->shows,$this->settings]);$updater=static fn()=>throw new RuntimeException('timestamp updater invoked');$service=new DataExportService($this->public,$status,static fn()=>new DateTimeImmutable('2026-09-01T12:34:56.987Z'),static fn()=>str_repeat('A',40),new PublicationUndoService($this->public,$undo,$updater));$destination=$this->root.'/export';$manifest=$service->export($destination);
        self::assertSame(1,$manifest['contract_version']);self::assertSame('2026-09-01T12:34:56.987Z',$manifest['created_at']);self::assertSame(str_repeat('a',40),$manifest['server_revision']);self::assertSame(['playlist_count'=>2,'show_count'=>2],$manifest['dataset']);self::assertSame(['one.json','two.json'],array_column($manifest['playlists'],'filename'));self::assertSame(['2026-08-01T10:00:00.000Z','2026-08-02T10:00:00.000Z'],array_column($manifest['playlists'],'published_at'));foreach(['config.json','playlists/index.json','playlists/one.json','playlists/two.json'] as $path){self::assertSame(file_get_contents($this->public.'/'.$path),file_get_contents($destination.'/'.$path));}$written=json_decode((string)file_get_contents($destination.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);self::assertSame($manifest,$written);foreach($manifest['files'] as $file){self::assertSame(hash_file('sha256',$destination.'/'.$file['path']),$file['sha256']);self::assertSame(filesize($destination.'/'.$file['path']),$file['bytes']);}self::assertDirectoryDoesNotExist($destination.'/thumbs');self::assertSame($beforePublic,$this->tree($this->public));self::assertSame($beforeRows,serialize([$this->playlists,$this->shows,$this->settings]));self::assertSame('preserve',file_get_contents($undo.'/history'));
    }

    public function testUnavailableRevisionBecomesNull():void{$manifest=(new DataExportService($this->public,$this->publicationStatus(),static fn()=>new DateTimeImmutable('2026-09-01Z'),static fn()=>null,new PublicationUndoService($this->public,$this->root.'/undo',static fn()=>throw new RuntimeException())))->export($this->root.'/export');self::assertNull($manifest['server_revision']);}

    #[DataProvider('statusFailureProvider')]
    public function testUnsynchronizedStatusesLeaveNoDestination(string $part):void
    {
        $status=$this->publicationStatus()->status();if($part==='playlist')$status['playlists'][0]['changed']=true;elseif($part==='config')$status['config']['changed']=true;elseif($part==='default')$status['default_playlist']['changed']=true;else $status['playlists']='bad';$destination=$this->root.'/failed';$this->expectException(PublicationException::class);try{(new DataExportService($this->public,new PermanentExportStatus($status),null,null,new PublicationUndoService($this->public,$this->root.'/undo-'.$part,static fn()=>throw new RuntimeException())))->export($destination);}finally{self::assertDirectoryDoesNotExist($destination);}
    }
    public static function statusFailureProvider():array{return array_map(static fn($x)=>[$x],['playlist','config','default','malformed']);}

    #[DataProvider('artifactFailureProvider')]
    public function testMalformedPublicationLeavesNoExport(string $case):void
    {
        if($case==='missing')unlink($this->public.'/playlists/one.json');elseif($case==='json')file_put_contents($this->public.'/playlists/one.json','{');elseif($case==='default'){$this->playlists[0]['is_default']=0;}elseif($case==='index mismatch'){$index=$this->decode('playlists/index.json');array_pop($index['playlists']);$this->write('playlists/index.json',$index);}elseif($case==='timestamp'){$index=$this->decode('playlists/index.json');$index['playlists'][0]['lastupdated']='2026-01-01T00:00:00.000Z';$this->write('playlists/index.json',$index);}
        $dest=$this->root.'/failed-'.$case;$this->expectException(PublicationException::class);try{(new DataExportService($this->public,$this->publicationStatus(),null,null,new PublicationUndoService($this->public,$this->root.'/undo-'.$case)))->export($dest);}finally{self::assertDirectoryDoesNotExist($dest);}
    }
    public static function artifactFailureProvider():array{return array_map(static fn($x)=>[$x],['missing','json','default','index mismatch','timestamp']);}

    public function testExistingDestinationsAndPublicationRootAreRejectedWithoutMutation():void
    {
        foreach(['empty','nonempty'] as $case){$dest=$this->root.'/'.$case;mkdir($dest);if($case==='nonempty')file_put_contents($dest.'/keep','bytes');$before=$this->tree($dest);try{(new DataExportService($this->public,$this->publicationStatus()))->export($dest);self::fail('Existing destination accepted');}catch(PublicationException){}self::assertSame($before,$this->tree($dest));}
        $this->expectException(PublicationException::class);(new DataExportService($this->public,$this->publicationStatus()))->export($this->public);
    }

    private function publicationStatus():PublicationStatusService{return new PublicationStatusService($this->public,fn()=>$this->playlists,fn(int $id)=>$this->shows[$id],fn()=>$this->settings);}
    private function write(string $path,array $value):void{file_put_contents($this->public.'/'.$path,json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");}
    private function decode(string $path):array{return json_decode((string)file_get_contents($this->public.'/'.$path),true,512,JSON_THROW_ON_ERROR);}
    private function tree(string $root):array{$out=[];if(!is_dir($root))return$out;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);foreach($it as $f)$out[substr($f->getPathname(),strlen($root)+1)]=$f->isFile()?hash_file('sha256',$f->getPathname()):'dir';ksort($out);return$out;}
}
