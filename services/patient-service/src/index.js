require('dotenv').config();
const express = require('express');
const cors = require('cors');
const routes = require('./routes');

const app = express();
const PORT = process.env.PORT || 3001;

app.use(cors());
app.use(express.json({ limit: '200kb' }));

app.get('/health', (_req,res)=>res.json({ status: 'ok' }));

app.use('/api/patients', routes); // Prefix /api/patients cho các route

app.listen(PORT, () => {
  console.log(`Patient service is running on http://localhost:${PORT}`);
});