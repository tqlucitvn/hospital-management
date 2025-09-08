const { MongoClient } = require('mongodb');

const uri = process.env.MONGO_URL || 'mongodb://root:secret@mongo:27017/?authSource=admin';
const dbName = process.env.MONGO_DB || 'hms_logs';

let clientPromise;

async function getClient() {
  if (!clientPromise) {
    const client = new MongoClient(uri, { maxPoolSize: 5 });
    clientPromise = client.connect();
  }
  return clientPromise;
}

async function insertLog(collection, doc) {
  try {
    const client = await getClient();
    const db = client.db(dbName);
    const col = db.collection(collection);
    await col.insertOne({ ...doc, ts: new Date() });
  } catch (e) {
    console.error('[mongoLog] insert failed', e.message);
  }
}

module.exports = { insertLog };