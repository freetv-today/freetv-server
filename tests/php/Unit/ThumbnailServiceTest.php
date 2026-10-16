<?php
declare(strict_types=1);
require_once __DIR__.'/../../../public/api/admin/ThumbnailService.php';
require_once __DIR__.'/../../../public/api/admin/DatasetPackage.php';
use FreeTV\Admin\DatasetPackage;use FreeTV\Admin\ThumbnailService;use PHPUnit\Framework\Attributes\DataProvider;use PHPUnit\Framework\TestCase;
final class ThumbnailServiceTest extends TestCase
{
 private string $root;protected function setUp():void{$this->root=sys_get_temp_dir().'/freetv-thumb-utils-'.bin2hex(random_bytes(8));mkdir($this->root); }protected function tearDown():void{DatasetPackage::removeTree($this->root);}
 #[DataProvider('summaryProvider')]public function testSummary(int $shows,int $needs,int $existing,array $expected):void{self::assertSame($expected,ThumbnailService::calculateSummary($shows,$needs,$existing));}
 public static function summaryProvider():array{return ['shared three'=>[3,1,1,['number_of_shows'=>3,'number_of_thumbnails'=>1,'missing_thumbnails'=>0,'shared_thumbnails'=>2]],'historical documented hundred'=>[100,95,92,['number_of_shows'=>100,'number_of_thumbnails'=>92,'missing_thumbnails'=>3,'shared_thumbnails'=>5]],'additional hundred'=>[100,80,60,['number_of_shows'=>100,'number_of_thumbnails'=>60,'missing_thumbnails'=>20,'shared_thumbnails'=>20]],'empty'=>[0,0,0,['number_of_shows'=>0,'number_of_thumbnails'=>0,'missing_thumbnails'=>0,'shared_thumbnails'=>0]],'all present'=>[4,4,4,['number_of_shows'=>4,'number_of_thumbnails'=>4,'missing_thumbnails'=>0,'shared_thumbnails'=>0]],'missing shared'=>[5,2,0,['number_of_shows'=>5,'number_of_thumbnails'=>0,'missing_thumbnails'=>2,'shared_thumbnails'=>3]]];}
 #[DataProvider('imdbProvider')]public function testImdbValidation(mixed $value,bool $valid):void{self::assertSame($valid,ThumbnailService::isValidImdb($value));}
 public static function imdbProvider():array{return ['short'=>['tt1',true],'normal'=>['tt1234567',true],'long'=>['tt12345678901',true],'placeholder'=>['tt000000',true],'prefix'=>['123',false],'letters'=>['tt12a',false],'suffix'=>['tt123.jpg',false],'url'=>['https://imdb.com/title/tt123',false],'traversal'=>['../tt123',false],'empty'=>['',false],'null'=>[null,false],'punctuation'=>['tt-123',false]];}
 public function testPlaceholderIsNarrowAndStillSyntacticallyValid():void{self::assertTrue(ThumbnailService::isPlaceholderImdb('tt000000'));self::assertTrue(ThumbnailService::isValidImdb('tt000000'));self::assertFalse(ThumbnailService::isPlaceholderImdb('tt0000000'));}
 public function testThumbnailExistsUsesInjectedDirectory():void{file_put_contents($this->root.'/tt123.jpg','x');$service=new ThumbnailService($this->root);self::assertTrue($service->thumbnailExists('tt123'));self::assertFalse($service->thumbnailExists('tt124'));$this->expectException(InvalidArgumentException::class);$service->thumbnailExists('../bad');}
}
