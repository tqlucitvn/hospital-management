const amqp = require('amqplib');

const URL = process.env.RABBITMQ_URL || 'amqp://rabbitmq:5672';
let connection;
let channel;

async function delay(ms) {
    return new Promise(r => setTimeout(r, ms));
}

async function connectWithRetry(maxAttempts = 10) {
    let attempt = 1;
    while (attempt <= maxAttempts) {
        try {
            console.log(`[broker] connect attempt ${attempt}/${maxAttempts}`);
            const conn = await amqp.connect(URL);
            conn.on('close', () => {
                console.warn('[broker] connection closed');
                channel = undefined;
                connection = undefined;
            });
            conn.on('error', (e) => {
                console.error('[broker] connection error', e.message);
            });
            return conn;
        } catch (e) {
            if (attempt === maxAttempts) throw e;
            await delay(Math.min(5000, attempt * 500));
            attempt++;
        }
    }
}

async function ensureChannel() {
    if (channel) return channel;
    if (!connection) {
        connection = await connectWithRetry();
    }
    channel = await connection.createChannel();
    await channel.prefetch(10);
    return channel;
}

async function subscribe(exchange, patterns, handler, queueName) {
    const ch = await ensureChannel();
    await ch.assertExchange(exchange, 'topic', { durable: true });
    const q = await ch.assertQueue(queueName, { durable: true });
    if (!Array.isArray(patterns)) patterns = [patterns];
    for (const p of patterns) {
        await ch.bindQueue(q.queue, exchange, p);
    }
    console.log(`[broker] subscribed queue=${q.queue} exchange=${exchange} patterns=${patterns.join(',')}`);
    ch.consume(q.queue, async (msg) => {
        if (!msg) return;
        let payload;
        try {
            payload = JSON.parse(msg.content.toString());
        } catch {
            console.error('[broker] JSON parse error');
            return ch.nack(msg, false, false);
        }
        try {
            await handler(payload, msg.fields.routingKey, msg.properties);
            ch.ack(msg);
        } catch (e) {
            console.error('[broker] handler error', e.message);
            ch.nack(msg, false, false);
        }
    });
}

async function close() {
    try { await channel?.close(); } catch { }
    try { await connection?.close(); } catch { }
    channel = undefined;
    connection = undefined;
}

module.exports = { subscribe, close };