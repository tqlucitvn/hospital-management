const amqp = require('amqplib');

let connection = null;
let channel = null;
const RABBIT_URL = process.env.RABBITMQ_URL || 'amqp://rabbitmq:5672';

async function getChannel() {
  if (channel) return channel;
  if (!connection) {
    connection = await amqp.connect(RABBIT_URL);
    connection.on('close', () => {
      connection = null;
      channel = null;
    });
    connection.on('error', () => {
      connection = null;
      channel = null;
    });
  }
  channel = await connection.createChannel();
  return channel;
}

async function publishEvent(exchange, routingKey, payload) {
  const ch = await getChannel();
  await ch.assertExchange(exchange, 'topic', { durable: true });
  ch.publish(
    exchange,
    routingKey,
    Buffer.from(JSON.stringify(payload)),
    { contentType: 'application/json', persistent: true }
  );
}

async function subscribe(exchange, patterns, handler) {
  const ch = await getChannel();
  await ch.assertExchange(exchange, 'topic', { durable: true });
  const q = await ch.assertQueue('', { exclusive: true });
  if (!Array.isArray(patterns)) patterns = [patterns];
  for (const p of patterns) {
    await ch.bindQueue(q.queue, exchange, p);
  }
  ch.consume(q.queue, async (msg) => {
    if (!msg) return;
    try {
      const data = JSON.parse(msg.content.toString());
      await handler(data, msg);
      ch.ack(msg);
    } catch {
      ch.nack(msg, false, false);
    }
  });
}

module.exports = { publishEvent, subscribe };