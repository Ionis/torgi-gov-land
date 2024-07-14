<?php

require_once '../vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

$data = getData('0c5b2444-70a0-4932-980c-b4dc0d3f02b5', 20);

if ($data) {
    $mysqli = new mysqli('localhost', 'my_user', 'my_password', 'my_db');

    if ($mysqli->connect_error) {
        die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $stmt = $mysqli->prepare("SELECT * FROM torgi_gov WHERE lot_id = ? LIMIT 1");

    foreach ($data as $item) {
        $stmt->bind_param("s", $item['id']);
        $stmt->execute();

        if($stmt->get_result()->num_rows == 0) {
            $stm = $mysqli->prepare("INSERT INTO torgi_gov SET lot_id = ?");
            $stm->bind_param("s", $item['id']);
            $stm->execute();

            $link = 'https://torgi.gov.ru/new/public/lots/lot/' .$item['id']. '/(lotInfo:info)?fromRec=false';
            sendMessage("{$item['biddForm']}\n\n<b>{$item['lotName']}</b>\n{$item['category']}\n\n{$item['lotDescription']}\n\n&#128073;  <a href='{$link}'>Подробнее</a>");
        }
    }
}

/**
 * Gets an array of auction data
 * @param string $fiasGUID Locality ID
 * @param int $size
 * @return array|string
 */
function getData(string $fiasGUID, int $size = 20): array|string
{
    $uri = 'https://torgi.gov.ru/new/api/public/lotcards/search?fiasGUID=' .$fiasGUID. '&byFirstVersion=true&withFacets=true&size=' .$size. '&sort=firstVersionPublicationDate,desc';

    try {
        $client = HttpClient::create();

        $response = $client->request(
            'GET',
            $uri
        );

        $content = $response->toArray();
        return preparation($content['content']);
    } catch (TransportExceptionInterface $e) {
        return $e->getMessage();
    }
}

function preparation($array): array
{
    $result = [];

    foreach ($array as $item) {
        $data = [
            'id' => $item['id'],
            'biddForm' => $item['biddForm']['name'],
            'lotName' => $item['lotName'],
            'lotDescription' => $item['lotDescription'],
            'category' => $item['category']['name']
        ];

        array_push($result, $data);
    }

    return $result;
}

function sendMessage($msg): void
{
    $botApiToken = 'YOUR BOT TOKEN'; // токен бота

    $data = [
        'chat_id' => '000000000', // название канала
        'text' => $msg,
        'parse_mode' => 'HTML'
    ];

    file_get_contents("https://api.telegram.org/bot{$botApiToken}/sendMessage?" .http_build_query($data));
}