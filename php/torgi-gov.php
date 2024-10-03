<?php

require_once '../vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

$dotenv = Dotenv\Dotenv::createImmutable("..");
$dotenv->load();

$data = getData('0c5b2444-70a0-4932-980c-b4dc0d3f02b5', 20);

if ($data) {
    $mysqli = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_NAME']);

    if ($mysqli->connect_error) {
        die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    $stmt = $mysqli->prepare("SELECT * FROM torgi_gov WHERE lot_id = ? LIMIT 1");

    foreach ($data as $item) {
        $stmt->bind_param("s", $item['id']);
        $stmt->execute();

        $result = $stmt->get_result();

        if($result->num_rows == 0) {
            $stm = $mysqli->prepare("INSERT INTO torgi_gov SET lot_id = ?");
            $stm->bind_param("s", $item['id']);
            $stm->execute();
            $stm->close();

            $link = 'https://torgi.gov.ru/new/public/lots/lot/' .$item['id']. '/(lotInfo:info)?fromRec=false';
            sendMessage("{$item['biddForm']}\n\n<b>{$item['lotName']}</b>\n{$item['category']}\n\n{$item['lotDescription']}\n\n&#128073;  <a href='{$link}'>Подробнее</a>");
        }
        $result->free();
    }

    $stmt->close();
    $mysqli->close();
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
        // timeout Время ожидания ответа в секундах
        $client = HttpClient::create(['base_uri' => null, 'proxy' => null, 'json' => null, 'timeout' => 5, 'query' => []]);

        $response = $client->request(
            'GET',
            $uri,
            [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/png,image/svg+xml,*/*;q=0.8',
                ],
            ]
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
    $botApiToken = $_ENV['TG_TOKEN']; // токен бота

    $data = [
        'chat_id' => '000000000', // название канала
        'text' => $msg,
        'parse_mode' => 'HTML'
    ];

    file_get_contents("https://api.telegram.org/bot{$botApiToken}/sendMessage?" .http_build_query($data));
}