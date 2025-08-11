require('dotenv').config();
const express = require('express');
const cors = require('cors');
const routes = require('./routes');
const errorHandler = require('./middleware/error');

const app = express();
const PORT = process.env.PORT || 3003;

app.use(cors());
app.use(express.json());

app.get('/health', (_req, res) => res.json({ status: 'ok' }));
app.use('/api/appointments', routes);
app.use(errorHandler);

app.listen(PORT, () => {
    console.log(`Appointment service on http://localhost:${PORT}`);
});