module.exports = async function handlePrescriptionEvent(payload, routingKey) {
    console.log(`[NOTIFY] ${routingKey} prescriptionId=${payload.id} status=${payload.status || ''} items=${payload.itemsCount || ''}`);
};