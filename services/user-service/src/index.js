require('dotenv').config();
const express = require('express');
const cors = require('cors');
const routes = require('./routes');

const app = express();
const PORT = process.env.PORT || 3002;

app.use(cors());
app.use(express.json());

app.use('/api/users', routes); // Prefix /api/users cho các route của service này

app.listen(PORT, () => {
    console.log(`User service is running on http://localhost:${PORT}`);
});