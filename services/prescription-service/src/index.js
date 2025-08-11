require('dotenv').config();
const express = require('express');
const cors = require('cors');
const routes = require('./routes');
const app = express();
const PORT = process.env.PORT || 3005;
const requestContext = require('./middleware/requestContext');
const accessLog = require('./middleware/accessLog');

app.use(cors());
app.use(express.json());

app.use(requestContext);
app.use(accessLog);

app.get('/health', (_req, res) => res.json({ status: 'ok' }));
app.use('/api/prescriptions', routes);

app.listen(PORT, () => console.log(`Prescription service on ${PORT}`));