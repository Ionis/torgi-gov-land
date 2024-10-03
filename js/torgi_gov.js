const axios = require('axios');
const { SocksProxyAgent } = require('socks-proxy-agent');
const https = require('https');
const mysql = require('mysql2');
// require('dotenv').config({ path: '../.env' });

const config = require('config');

const TG_TOKEN = config.get('TG_TOKEN');
const TG_CLIENT = config.get('TG_CLIENT');
const DB_HOST = config.get('DB_HOST');
const DB_USER = config.get('DB_USER');
const DB_PASSWORD = config.get('DB_PASSWORD');
const DB_NAME = config.get('DB_NAME');
const PROXY_URL = config.get('PROXY_URL');

const TelegramBotClient = require('telegram-bot-client');
const client = new TelegramBotClient(TG_TOKEN);

// Прокси и HTTPS-агенты
const proxyAgent = new SocksProxyAgent(PROXY_URL); // Перенос данных прокси в .env файл
const httpsAgent = new https.Agent({ rejectUnauthorized: false }); // Отключение проверки SSL

// Настройка соединения с базой данных
const connection = mysql.createPool({
    connectionLimit: 1000,
    connectTimeout: 60 * 60 * 1000,
    host: DB_HOST,
    user: DB_USER,
    password: DB_PASSWORD,
    database: DB_NAME
}).promise();

(async () => {
    try {
        const data = await getData(10); // 10 - количество запрашиваемых лотов
        for (const item of data) {
            const [result] = await connection.query('SELECT * FROM `torgi_gov` WHERE `lot_id` = ? LIMIT 1', [item.id]);
            if (result.length === 0) {  // Если записи в б.д. нет, сохраняем
                await saveDb(item);
                const link = `https://torgi.gov.ru/new/public/lots/lot/${item.id}/(lotInfo:info)?fromRec=false`;
                await sendMessage(`${item.biddForm}:\n\n${item.lotName}\n${item.category}\n\n${item.lotDescription}\n${link}`);
            }
        }
    } catch (error) {
        // console.error('Error in the main process:', error.message);
        await sendMessage('Error in the main process:' + error.message);
    } finally {
        await connection.end();
    }
})();

async function getData(size = 20) {
    try {
        const response = await axios.get('https://torgi.gov.ru/new/api/public/lotcards/search', {
            params: {
                dynSubjRF: 3,
                catCode: 2,
                matchPhrase: false,
                fiasGUID: 'b3d2e10f-752d-4b90-b54b-9d6545f38ae0',
                byFirstVersion: true,
                withFacets: true,
                size: size,
                sort: 'firstVersionPublicationDate,desc'
            },
            responseType: 'json',
            headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0' },
            httpsAgent: httpsAgent,
            httpAgent: proxyAgent,
            timeout: 5000   // Устанавливаем таймаут в 5 секунд
        });

        return preparation(response.data.content);
    } catch (error) {
        // console.error('Error in getData():', error.message);
        await sendMessage('Error in getData():' + error.message);
    }
}

function preparation(array) {
    return array.map(el => ({
        id: el.id,
        biddForm: el.biddForm.name,
        lotName: el.lotName,
        lotDescription: el.lotDescription,
        category: el.category.name
    }));
}

async function saveDb(data) {
    try {
        const [result] = await connection.query('INSERT INTO torgi_gov (lot_id) VALUES (?)', [data.id]);
        return result.insertId;
    } catch (err) {
        await sendMessage(err.message);
        // sendMessage(err.stack);
        // console.log(err);
    }
}

async function sendMessage(msg) {
    try {
        const opts = { parse_mode: 'html' };
        const response = await client.sendMessage(TG_CLIENT, msg, opts).promise();
        return response.result.message_id;
    } catch (err) {
        console.error('Error in sendMessage():', err);
    }
}