const amqp = require('amqplib');
let conn, ch;
const URL = process.env.RABBITMQ_URL || 'amqp://rabbitmq:5672';

async function channel() {
    if (ch) return ch;
    conn = await amqp.connect(URL);
    conn.on('close', () => { ch = null; conn = null; });
    ch = await conn.createChannel();
    return ch;
}

async function publishEvent(exchange, rk, payload) {
    const c = await channel();
    await c.assertExchange(exchange, 'topic', { durable: true });
    c.publish(exchange, rk, Buffer.from(JSON.stringify(payload)), {
        contentType: 'application/json',
        persistent: true
    });
}

module.exports = { publishEvent };