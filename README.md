# Hospital Management Backend (v0)

## 1. Tổng quan
Hệ thống microservices quản lý:
- User & phân quyền (ADMIN, DOCTOR, NURSE, RECEPTIONIST)
- Patient
- Appointment (chống trùng lịch bác sĩ)
- Prescription (đơn thuốc + items, quy tắc chuyển trạng thái)
- Notification service (consume event từ RabbitMQ)

Hiện đang ở bản phát triển (prototype) ưu tiên tính năng core, chưa hardening bảo mật / migrate chuẩn.

## 2. Kiến trúc
Services (tách code + DB riêng):
- user-service
- patient-service
- appointment-service
- prescription-service
- notification-service (consumer)
Infra:
- PostgreSQL (mỗi service 1 DB)
- RabbitMQ (topic exchanges)
Giao tiếp:
- REST (HTTP JSON)
- Event async (RabbitMQ topic): appointment.events, prescription.events

## 3. Công nghệ
Node.js 18, Express, Prisma (db push tạm thời), JWT, RabbitMQ (amqplib), Docker Compose.

## 4. Chạy nhanh (Windows PowerShell / Git Bash)
```bash
# Clone
git clone <repo>
cd hospital-managemnent/backend

# Build & chạy
docker compose up --build -d

# Xem logs 1 service
docker compose logs -f appointment-service
```
RabbitMQ UI: http://localhost:15672 (guest/guest)

## 5. Ports & Services
- user-service: 3002
- patient-service: 3001
- appointment-service: 3003
- prescription-service: 3005
- notification-service: (no public API, chỉ log)
- RabbitMQ: 5672 / mgmt 15672
- PostgreSQL DBs: 5433..5436 (host mapped)

## 6. Env tối thiểu (mỗi service .env)
```
DATABASE_URL=postgresql://admin:password@<service-db>:5432/<db_name>
JWT_SECRET=your-super-secret-key-for-jwt
RABBITMQ_URL=amqp://rabbitmq:5672
PORT=<service_port>
```

## 7. Luồng nghiệp vụ chính
1. ADMIN đăng ký / đăng nhập user (login lấy JWT).
2. Tạo Patient.
3. Tạo Appointment (kiểm tra không trùng lịch).
4. Cập nhật / hủy Appointment (status).
5. Tạo Prescription (kèm items).
6. Cập nhật Prescription status (ISSUED → FILLED / CANCELED).
7. Notification-service nhận & log event:
   - appointment.created / statusUpdated / deleted
   - prescription.created / statusUpdated

## 8. API chính (tóm tắt)
(Headers: Authorization: Bearer <JWT> khi cần)

User-service (http://localhost:3002/api/users):
- POST /register { email, password, role }
- POST /login { email, password } -> { token }
- GET / (ADMIN) list users
- PATCH /:id/role (ADMIN)

Patient-service (http://localhost:3001/api/patients) (giả định):
- POST / (create patient)
- GET /:id
- GET /
- PATCH /:id
- DELETE /:id (nếu có)

Appointment-service (http://localhost:3003/api/appointments):
- POST / { patientId, doctorId, startTime, endTime, reason? }
- GET /
- PATCH /:id/status { status } (SCHEDULED|COMPLETED|CANCELED)
- DELETE /:id

Prescription-service (http://localhost:3005/api/prescriptions):
- POST / { patientId, doctorId, appointmentId?, note?, items[] }
  items: [{ drugName,dosage,frequency,durationDays,instruction? }]
- GET /?patientId=...
- GET /:id
- PATCH /:id/status { status } (ISSUED|FILLED|CANCELED)

## 9. Event Model
Exchanges:
- appointment.events (topic)
  - appointment.created
  - appointment.statusUpdated
  - appointment.deleted
- prescription.events (topic)
  - prescription.created
  - prescription.statusUpdated

Payload (ví dụ):
```json
{
  "type": "appointment.created",
  "id": "cuid",
  "patientId": "xxx",
  "doctorId": "xxx",
  "startTime": "2025-08-14T09:00:00.000Z",
  "endTime": "2025-08-14T09:30:00.000Z",
  "status": "SCHEDULED",
  "correlationId": "uuid",
  "requestId": "uuid",
  "ts": "2025-08-11T..."
}
```

## 10. Kiểm tra nhanh
```bash
# Đăng nhập lấy token
curl -X POST http://localhost:3002/api/users/login -H "Content-Type: application/json" -d '{"email":"admin@x.com","password":"pass"}'

# Tạo appointment
curl -X POST http://localhost:3003/api/appointments \
  -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"patientId":"PATIENT_ID","doctorId":"DOCTOR_ID","startTime":"2025-08-14T09:00:00Z","endTime":"2025-08-14T09:30:00Z"}'
```

## 11. Ràng buộc / Validation hiện tại
- Appointment: bắt buộc trường, parse thời gian, end > start, không overlap (doctor).
- Prescription: items bắt buộc, status transition ISSUED→FILLED/CANCELED.
- User: unique email, (chưa áp dụng password/email format nâng cao).
- Patient: phoneNumber @unique (theo schema nội bộ).
- requestId & correlationId: Appointment + Prescription services.

## 12. Giới hạn hiện tại
- Dùng prisma db push (chưa migrate versioned).
- Chưa có test tự động.
- Error format chưa thống nhất toàn hệ thống.
- Chưa có rate limit / helmet / audit log.
- Logging chưa đồng bộ ở user/patient-service.
- Chưa có thống kê / báo cáo.

## 13. Hướng phát triển tiếp
1. Chuyển sang prisma migrate (migrations version control).
2. Chuẩn hóa error + validation (Zod/Joi).
3. Thêm correlationId cho mọi event (nếu mở rộng).
4. Viết test (Jest + supertest).
5. README chi tiết schema & ERD.
6. Tracing / metrics (OpenTelemetry / Prometheus).
7. Rate limit & security headers.
8. Enum hoá status trong schema (AppointmentStatus, PrescriptionStatus).

## 14. Troubleshooting
- Notification log trùng: có 2 consumer attach cùng queue → thêm guard hoặc khởi động lại.
- ECONNREFUSED RabbitMQ: đảm bảo healthcheck rabbitmq OK, retry logic trong broker đã có.
- Overlap appointment vẫn tạo: kiểm tra timezone và đầu vào start/end đúng ISO.

## 15. Cleanup / Reset DB nhanh
```bash
docker compose down -v
docker compose up --build -d
```
## 16. Các Luồng Nghiệp Vụ (Trình Tự Gọi API)

### Luồng 1: Khởi tạo hệ thống lần đầu
1. (Tuỳ chọn) Tạo admin đầu tiên  
   POST /api/users/register { email, password, role:"ADMIN" }
2. Đăng nhập admin  
   POST /api/users/login -> token
3. Tạo các tài khoản nhân sự khác (bác sĩ, y tá, lễ tân)  
   POST /api/users/register (Bearer token ADMIN)
4. (Tuỳ chọn) Điều chỉnh role  
   PATCH /api/users/:id/role { role }

### Luồng 2: Tiếp nhận bệnh nhân (Onboarding)
1. Lễ tân (hoặc role hợp lệ) đăng nhập
2. Tạo bệnh nhân  
   POST /api/patients { fullName, dateOfBirth, gender, phoneNumber, ... }
3. (Tuỳ chọn) Xem / tìm kiếm bệnh nhân  
   GET /api/patients?search=...

### Luồng 3: Đặt lịch khám (Appointment)
1. Đăng nhập (RECEPTIONIST / ADMIN / DOCTOR nếu cho phép)
2. Có sẵn patientId & doctorId (nếu chưa có quay lại Luồng 2)
3. Tạo lịch  
   POST /api/appointments { patientId, doctorId, startTime, endTime, reason? }
   - 201: thành công  
   - 409: trùng khung giờ bác sĩ (Doctor timeslot conflict)
4. (Tuỳ chọn) Liệt kê lịch sắp tới  
   GET /api/appointments

### Luồng 4: Hoàn tất lịch & lập đơn thuốc
1. Bác sĩ đăng nhập
2. (Tuỳ chọn) Kiểm tra lịch chi tiết
3. Đánh dấu hoàn tất  
   PATCH /api/appointments/:id/status { status:"COMPLETED" }
4. Tạo đơn thuốc  
   POST /api/prescriptions { patientId, doctorId, appointmentId?, note?, items:[...] }
5. Xem chi tiết đơn (tuỳ chọn)  
   GET /api/prescriptions/:id

### Luồng 5: Huỷ lịch khám
1. Đăng nhập (RECEPTIONIST / ADMIN / DOCTOR nếu cho phép)
2. PATCH /api/appointments/:id/status { status:"CANCELED" }

### Luồng 6: Cấp phát (FILLED) đơn thuốc
1. Bác sĩ hoặc ADMIN đăng nhập
2. PATCH /api/prescriptions/:id/status { status:"FILLED" }
   - Đã FILLED gửi lại: 200 (idempotent)
   - Đang CANCELED: 409 (invalid transition)

### Luồng 7: Huỷ đơn thuốc
1. Bác sĩ hoặc ADMIN đăng nhập
2. PATCH /api/prescriptions/:id/status { status:"CANCELED" }
   - Chỉ cho phép khi đang ISSUED

### Luồng 8: Tổng quan hồ sơ bệnh nhân
1. Đăng nhập (DOCTOR / NURSE / ADMIN)
2. GET /api/patients/:id
3. GET /api/appointments (FE tạm lọc theo patientId)
4. GET /api/prescriptions?patientId=...

### Luồng 9: Quản trị người dùng
1. ADMIN đăng nhập
2. GET /api/users
3. PATCH /api/users/:id/role { role }

### Luồng 10: Debug sự kiện
1. Thực hiện hành động tạo/ cập nhật (appointment hoặc prescription)
2. Xem logs notification-service:
   - appointment.created / statusUpdated / deleted
   - prescription.created / statusUpdated

### Mẫu lỗi chính
- 400: Thiếu / sai định dạng dữ liệu
- 401: Thiếu hoặc sai token
- 403: Không đủ quyền
- 404: Không tìm thấy
- 409: 
  - Trùng khung giờ bác sĩ
  - Chuyển trạng thái không hợp lệ
  - Email trùng (user)
- 500: Lỗi server (hiển thị thông báo chung)

### Gợi ý retry phía FE
- 409 (conflict): không auto retry – yêu cầu người dùng chọn lại
- 500: cho phép thử lại thủ công
- Mất mạng: backoff (2s,4s,8s) cho GET không đột biến dữ liệu

### Dữ liệu tối thiểu cần cho mỗi luồng
| Luồng | ID bắt buộc |
|-------|-------------|
| Đặt lịch | patientId, doctorId |
| Lập đơn | patientId, doctorId (appointmentId tuỳ chọn) |
| Hoàn tất lịch | appointmentId |
| Cấp phát đơn | prescriptionId |

### Chuyển trạng thái
- Appointment: SCHEDULED -> COMPLETED / CANCELED (không quay lại)
- Prescription: ISSUED -> FILLED / CANCELED (không quay lại)

### Gợi ý cache phía FE
- Cache danh sách bác sĩ (users role=DOCTOR)
- Cache thông tin bệnh nhân vừa xem
- Invalidate danh sách lịch khi: tạo, đổi status, xoá

### Tham số query tương lai (dự kiến)
- /api/appointments?doctorId=&from=&to=&status=
- /api/prescriptions?patientId=&status=

## 17. Sequence Diagrams

### 17.1 Create Appointment

```mermaid
sequenceDiagram
  participant FE as Frontend
  participant US as User-Service (JWT verify)
  participant AS as Appointment-Service
  participant MQ as RabbitMQ
  participant NS as Notification-Service

  FE->>US: POST /api/users/login
  US-->>FE: 200 {token}
  FE->>AS: POST /api/appointments (Bearer token)
  AS->>AS: Validate + check overlap
  AS-->>FE: 201 {appointment}
  AS->>MQ: publish appointment.created
  MQ-->>NS: appointment.created
  NS->>NS: Log notification
```

### 17.2 Complete Appointment & Issue Prescription
```mermaid
sequenceDiagram
  participant FE
  participant AS as Appointment-Service
  participant PS as Prescription-Service
  participant MQ as RabbitMQ
  participant NS as Notification-Service

  FE->>AS: PATCH /appointments/:id/status {COMPLETED}
  AS-->>FE: 200
  AS->>MQ: appointment.statusUpdated
  FE->>PS: POST /prescriptions {...items}
  PS-->>FE: 201 {prescription}
  PS->>MQ: prescription.created
  MQ-->>NS: events
  NS->>NS: Log
```

### 17.3 Update Prescription Status (ISSUED -> FILLED)
```mermaid
sequenceDiagram
  participant FE
  participant PS as Prescription-Service
  participant MQ as RabbitMQ
  participant NS as Notification-Service

  FE->>PS: PATCH /prescriptions/:id/status {FILLED}
  PS->>PS: Validate transition
  PS-->>FE: 200 {status:FILLED}
  PS->>MQ: prescription.statusUpdated
  MQ-->>NS: event
  NS->>NS: Log
```

--- 
Version: v0 (prototype).
 