<?php
require_once('./config.php');


$accessToken = $token["line"];

//ユーザーからのメッセージ取得
$json_string = file_get_contents('php://input');
$json_object = json_decode($json_string);
$replyToken = $json_object->{"events"}[0]->{"replyToken"};
$message_type = $json_object->{"events"}[0]->{"message"}->{"type"};
$message_text = $json_object->{"events"}[0]->{"message"}->{"text"};


//ユーザー名の取得
$options = array(
    'http'=>array(
        'method'=>'GET',
        'header'=>"Authorization: Bearer ".$accessToken
    )
);

$context = stream_context_create( $options );
$contents = file_get_contents( 'GET https://api.line.me/v2/profile', FALSE, $context );
$user_info = json_decode($contents);
$user_name = $user_info->{"displayName"};

//返信メッセージ

//ジャンケン
$hash = array('グー', 'チョキ', 'パー' );
$key = array_rand($hash);
$jan = $hash[$key];

if(($message_text == "グー" && $jan == "チョキ") || ($message_text == "チョキ" && $jan == "パー") || ($message_text == "パー" && $jan == "グー"))
{
    $return_message_text = "僕は" . $jan . "をだしたよ！おめでとう！君の勝ち！";
}
else if($message_text == $jan)
{
    $return_message_text = "僕は" . $jan . "をだしたよ！あいこだね！もう一回頑張ろう！！";
}
else if(($message_text == "グー" && $jan == "パー") || ($message_text == "チョキ" && $jan == "グー") || ($message_text == "パー" && $jan == "チョキ"))
{
    $return_message_text = "僕は" . $jan . "をだしたよ！残念！君の負け！";
}
else if(preg_match("/わーすた/",$message_text))
{
    $return_message_text="わーすたが最強なんや";
}

else if(preg_match("/嫌い/",$message_text))
{
    $return_message_text="俺は好きだよ";
}
//天気
else if($message_text == "おはよう")
{
    $tmp_url = "http://weather.livedoor.com/forecast/webservice/json/v1?city=280010";
    $json = file_get_contents($tmp_url,true) or die("Failed to get json");
    $json = mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
    $weather_obj = json_decode($json);
    $weather = $weather_obj->forecasts[0]->image->title;
    $return_message_text = "おはようございます！今日の神戸市の天気は".$weather."です 今日も一日頑張りましょう";
}
else if(preg_match("/に行きたい$/",$message_text) || preg_match("/にいきたい$/",$message_text))
{
    //ぐるなびAPI
    $key = mb_substr($message_text, 0, -5);
    $uri   = "http://api.gnavi.co.jp/RestSearchAPI/20150630/";
    $params = [
        'keyid' => $token["grunavi"],
        'format' => 'json',
        'freeword' => $key,
        'hit_per_page' => 5
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $uri . '?' . http_build_query($params, '', '&'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FAILONERROR => true,
        CURLOPT_ENCODING => 'gzip',
    ]);
    $response = curl_exec($ch);
    $gnavi_obj = json_decode($response);

    $cnt = $gnavi_obj->total_hit_count;

    if($gnavi_obj->error || $cnt < 5)
    {
        $return_message_text = $key."に該当する飲食店は見つかりませんでした 別の検索ワードをお試しください";
    }

    else
    {
        $return_message_text = "成功";
        $message_type = "carou";
        foreach($gnavi_obj->rest as $r)
        {
            $image[] = htmlspecialchars($r->image_url->shop_image1);
            $name[] = htmlspecialchars($r->name);
            $url[] = htmlspecialchars($r->url);
            $info[] = htmlspecialchars($r->address);
        }
    }
}


else
//雑談対話
{
    $api_key = $token["zatsudantaiwa"];
    $api_url = sprintf('https://api.apigw.smt.docomo.ne.jp/dialogue/v1/dialogue?APIKEY=%s', $api_key);
    $req_body = array('utt' => $message_text, 'nickname' => $user_name );
    $headers = array(
        'Content-Type: application/json; charset=UTF-8',
    );
    $options = array(
        'http'=>array(
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => json_encode($req_body),
            )
        );
    $stream = stream_context_create($options);
    $res = json_decode(file_get_contents($api_url, false, $stream));
    $return_message_text = $res->utt;
}

//返信
if($message_type == "text")
{
    $response_format_text = [
        "type" => $message_type,
        "text" => $return_message_text
    ];

    $post_data = [
        "replyToken" => $replyToken,
        "messages" => [$response_format_text]
    ];
}


//ぐるなび
else if($message_type == "carou")
{

//actionの設定
    $action0 = [
        "type" => "uri",
        "label" => "詳細",
        "uri" => $url[0]
    ];
    $action1 = [
        "type" => "uri",
        "label" => "詳細",
        "uri" => $url[1]
    ];
    $action2 = [
        "type" => "uri",
        "label" => "詳細",
        "uri" => $url[2]
    ];
    $action3 = [
        "type" => "uri",
        "label" => "詳細",
        "uri" => $url[3]
    ];
    $action4 = [
        "type" => "uri",
        "label" => "詳細",
        "uri" => $url[4]
    ];
//columnsの設定

    $columns0 = [
        "thumbnailImageUrl" => $image[0],
        "title" => $name[0],
        "text" => $info[0],
        "actions" => [$action0]
    ];
    $columns1 = [
    "thumbnailImageUrl" => $image[1],
    "title" => $name[1],
    "text" => $info[1],
    "actions" => [$action1]
    ];
    $columns2 = [
    "thumbnailImageUrl" => $image[2],
    "title" => $name[2],
    "text" => $info[2],
    "actions" => [$action2]
    ];
    $columns3 = [
    "thumbnailImageUrl" => $image[3],
    "title" => $name[3],
    "text" => $info[3],
    "actions" => [$action3]
    ];
    $columns4 = [
    "thumbnailImageUrl" => $image[4],
    "title" => $name[4],
    "text" => $info[4],
    "actions" => [$action4]
    ];

    $template = [
        "type" => "carousel",
        "columns" => [$columns0,$columns1,$columns2,$columns3,$columns4]
    ];


    $response_format_carou = [
        "type" => "template",
        "altText" => "「".$key."」の検索結果が送信されました",
        "template" => $template
    ];


    $post_data = [
        "replyToken" => $replyToken,
        "messages" => [$response_format_carou]
    ];

}

else if($message_type == "image" || $message_type == "sticker")
//スタンプ
{
    $return_message_text = "スタンプ";
    $response_format_sticker = [
        "type" => "sticker",
        "packageId" => "2",
        "stickerId" => "149"
    ];
    $post_data = [
        "replyToken" => $replyToken,
        "messages" => [$response_format_sticker]
    ];
}


$ch = curl_init("https://api.line.me/v2/bot/message/reply");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json; charser=UTF-8',
    'Authorization: Bearer ' . $accessToken
));
$result = curl_exec($ch);
curl_close($ch);
?>
