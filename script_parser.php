<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<?php
date_default_timezone_set('Etc/GMT+3');
$url = $_POST['url'];
if(empty($url)){
  exit('Введите ссылку');
}
$name_file = date("Y-m-d_H_i_s");

$count_page1 = $_POST['count_page1']; //Страница "от"
$count_page2 = $_POST['count_page2'];
if(empty($count_page1)){
  $count_page1 = 1;
}
if(empty($count_page2)){
  $count_page2 = 1;
}
$count_post = $count_page1 * 30 - 29; //Первый номер поста на странице. 
$uagent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36";

$i = $count_page1;
while($i <= $count_page2){
  $url = $url.'/page-'.$i;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERAGENT, $uagent);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
$html = curl_exec($ch);
if(empty($html)){
    echo 'Ошибка curl: ' . curl_error($ch);
    echo ' Возможно такой страницы нет ';
    echo $url;
}else { // получили ответ от сайта, парсим данные
    preg_match_all('#<h1(.+?)>(.+?)</h1>#',$html,$result_title);//Это название темы (но не точно)
    preg_match_all('#<span itemprop="author"(.+?)<meta itemprop="name" content="(.+?)">(.+?)Отправлено(.+?)>(.+?)</abbr>(.+?)<div itemprop="text"(.+?)>(.+?)<\!--topic --->#si',$html,$result_table);

    // массив для цикла прохождения по регулярке, ключи = (.+?)
    $arr_foreach = [
                2 => ['name' => 'name'],
                5 => ['name' => 'date'],
                8 => ['name' => 'text'],
                9 => ['name' => 'text2']
               ];
    $array_results['title'] = $result_title[2][0];
    // проходимся по массиву
    foreach($arr_foreach as $key_f => $fore){
      $name = $fore['name'];
      // сохраняем данные с регулярки
      if(!empty($result_table[$key_f])){
        foreach($result_table[$key_f] as $key => $ress){
          if(isset($ress)){
              $array_results[$key][$name] = trim($ress);
          }  else {
              $array_results[$key][$name] = '-';
          }
        }
      }
    }

}
curl_close($ch);

// если получили массив с данными
if(!empty($array_results)){
  $add_file = null;
  foreach($array_results as $one_result){

    // тут сохраняем название темы 
    if(!is_array($one_result)){
        $add_file .= "$one_result страница номер $i\r\n\r\n";
        continue;
    }
      // заносим весь текст (с тегами) в переменную
      $text = $one_result['text'];

      // находим имя автора темы или сообщения
      if(isset($one_result['name'])){
        $name = $one_result['name'];
        $add_file .= "$name";
      }

      // находим дату
      if(isset($one_result['date'])){
        $date = $one_result['date'];
        // разделяем дату и время
        $exp = explode("-", $date);
        $date = isset($exp[0]) ? $exp[0] : "00-00-0000";
        $time = isset($exp[1]) ? $exp[1] : "00:00:00";
        $date = explode(" ", trim($date));
        $date = implode("-", $date);
/*        $add_file .= " - отправлено $date$time\r\n";*/
        $add_file .= " - пост № $count_post\r\n"; // Просто больше не пишем дату, а пишем посты по порядку. Знаю, что это неправильно, но увы :(
        $count_post++; // Счетчик постов
      }

      // регулярка, ищем = patterns , заменяем на replacements 
      $patterns = array();
      $patterns = ["/<div class=\"signature(.*)/s",
                   "/&nbsp;/",
                   "/\n/",
                   "/<blockquote(.+?)data-author=\"(.+?)\"/",
                   "/<\/blockquote>/",
                   "/<img(.+?)bbc_emoticon(.+?)alt='(.+?)' \/>/"
                   //"/<img(.+?)bbc_img(.+?)alt=\"(.+?)\">/"
                    ];
      $replacements = ["",
                       "",
                       " ",
                       " \r\nцитата - \\2\r\n<blockquote\\1data-author=\"\\2\"",//
                       " \r\nконец цитаты\r\n</blockquote>",
                       "\\3"
                       //"\\3"
                       ];

      $text = preg_replace($patterns, $replacements, $text);

      // проверяем есть ли изображения
      if(preg_match_all("#<img(.+?)src=\"(.+?)\"#s",$text,$result_img)){
        $img = $result_img[2][0];
      }

      // проверяем есть ли видео
      if(preg_match_all('#<iframe#s',$text,$result_video)){
        $video = $result_video[0];
      }

      $text = trim(strip_tags($text));    // удаляем все оставшиеся теги

      $add_file .= "$text\r\n";

      if(isset($video) or isset($img)){
        $add_file .= ' смотри медиа'."\r\n\r\n";
        $img = null;
        $video = null;
      }

      $add_file .= "\r\n";
  }
  $add_file = preg_replace('/Array/','',$add_file);
  $fp = fopen($name_file.'.txt', 'a');
  fwrite($fp, $add_file . PHP_EOL);
  fclose($fp);
  unset($add_file);
}
  $i++;
  usleep(20000);
}
echo 'Готово. <a download href="'.$name_file.'.txt">'.$name_file.'.txt</a><br/><a href="/">На главную</a><br/></br>';
$files = glob('*.txt');
echo 'На Вашем сервере уже '.count($files).' текстовых файлов<br/><a href="clear.php">Очистить</a> (Будут удалены все txt файлы с сервера)';
/*$i = 0;
while ($i < count($files)) {
  echo $files[$i].'<br/>';
  $i++;
}*/