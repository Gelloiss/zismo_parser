<?
$files = glob('*.txt');
$i = 0;
while ($i < count($files)) {
  unlink($files[$i]);
  $i++;
}
header('location: /');