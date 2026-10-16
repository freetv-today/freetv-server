<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductionContentSnapshotEndpointTest extends TestCase
{
    private array $sessions=[];
    protected function tearDown():void{foreach($this->sessions as $root){foreach(glob($root.'/*')?:[] as $file)unlink($file);rmdir($root);}}

    #[DataProvider('boundaryProvider')]
    public function testEndpointRequestBoundaries(string $endpoint,string $method,?array $user,array $query,array $expected):void
    {
        $body=$this->runEndpoint($endpoint,$method,$user,$query);self::assertSame($expected,json_decode($body,true,512,JSON_THROW_ON_ERROR));
    }
    public static function boundaryProvider():array
    {
        $viewer=['id'=>1,'username'=>'viewer','role'=>'viewer'];$admin=['id'=>2,'username'=>'admin','role'=>'admin'];$name='freetv-content-snapshot-20260828T183426Z';
        return [
            'create unauthorized'=>['data-snapshot-create.php','POST',null,[],['success'=>false,'message'=>'Unauthorized']],
            'create viewer'=>['data-snapshot-create.php','POST',$viewer,[],['success'=>false,'message'=>'Forbidden']],
            'create method'=>['data-snapshot-create.php','GET',$admin,[],['success'=>false,'message'=>'Method not allowed']],
            'download unauthorized'=>['data-snapshot-download.php','GET',null,['snapshot'=>$name],['success'=>false,'message'=>'Unauthorized']],
            'download viewer'=>['data-snapshot-download.php','GET',$viewer,['snapshot'=>$name],['success'=>false,'message'=>'Forbidden']],
            'download method'=>['data-snapshot-download.php','POST',$admin,[],['success'=>false,'message'=>'Method not allowed']],
            'invalid name'=>['data-snapshot-download.php','GET',$admin,['snapshot'=>'../'.$name],['success'=>false,'message'=>'Invalid snapshot name']],
        ];
    }

    private function runEndpoint(string $endpointName,string $method,?array $user,array $query):string
    {
        $endpoint=realpath(__DIR__.'/../../../public/api/admin/'.$endpointName);$sessionRoot=sys_get_temp_dir().'/freetv-endpoint-session-'.bin2hex(random_bytes(8));mkdir($sessionRoot,0700);$this->sessions[]=$sessionRoot;
        $code='$_SERVER["REQUEST_METHOD"]='.var_export($method,true).';$_GET='.var_export($query,true).';';if($user!==null){$code.='ini_set("session.save_path",'.var_export($sessionRoot,true).');session_id("fixture");session_start();$_SESSION["admin"]='.var_export($user,true).';';}$code.='require '.var_export($endpoint,true).';';$process=proc_open([PHP_BINARY,'-d','display_errors=0','-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);self::assertIsResource($process);$out=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);return $out;
    }
}
