module.exports = async function handleAppointmentEvent(payload, routingKey) {
    console.log(`[NOTIFY] ${routingKey}`, payload);
    // TODO: send email / push / store audit
};