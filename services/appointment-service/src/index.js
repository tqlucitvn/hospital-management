require('dotenv').config();
const express = require('express');
const cors = require('cors');
const routes = require('./routes');
const errorHandler = require('./middleware/error');
const requestContext = require('./middleware/requestContext');
const accessLog = require('./middleware/accessLog');

const app = express();
const PORT = process.env.PORT || 3003;

app.use(cors());
app.use(express.json({ limit: '200kb' }));

app.use(requestContext);
app.use(accessLog);
app.get('/health', (_req, res) => res.json({ status: 'ok' }));
app.use('/api/appointments', routes);
app.use(errorHandler);

app.listen(PORT, () => {
    console.log(`Appointment service on http://localhost:${PORT}`);
});