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
- mongo (MongoDB for logs): 27017 (internal) / mapped 27017
- mongo-express (UI xem log): 8081
- mailhog (SMTP demo + UI email): SMTP 1025 / Web 8025
- RabbitMQ: 5672 / mgmt 15672
- PostgreSQL DBs: 5433..5436 (host mapped)

## 6. Env tối thiểu (mỗi service .env)
```
DATABASE_URL=postgresql://admin:password@<service-db>:5432/<db_name>
JWT_SECRET=your-super-secret-key-for-jwt
RABBITMQ_URL=amqp://rabbitmq:5672
PORT=<service_port>
# Logging (áp dụng cho services có ghi log)
MONGO_URL=mongodb://root:secret@mongo:27017/?authSource=admin
MONGO_DB=hms_logs
# Email demo (notification-service)
SMTP_HOST=mailhog
SMTP_PORT=1025
SMTP_SECURE=false
SMTP_FROM="Hospital HMS <noreply@hms.local>"
ENABLE_EMAIL=true
NOTIFY_DEMO_TO=demo@hms.local
```

## 6.1 Đồng bộ DB sau khi thay đổi schema (Appointment thêm CONFIRMED)
## 6.2 Logging (MongoDB)
Các service sau đã được tích hợp ghi log vào MongoDB:
- appointment-service: ghi access log HTTP (collection `access_logs`)
- notification-service: ghi sự kiện nhận từ RabbitMQ (collection `notification_events`)

Docker Compose bổ sung services:
```
mongo          (MongoDB 6.0, auth root / secret)
mongo-express  (UI quản lý tại http://localhost:8081 – basic auth root / secret)
```

Collections & Schema (tối giản / schemaless):
- access_logs: { method, path, status, durationMs, requestId?, correlationId?, ts }
- notification_events: { type, payload(embedded), ts }

Xem nhanh qua mongo-express:
1. Truy cập http://localhost:8081
2. Đăng nhập: root / secret
3. Chọn database `hms_logs` → chọn collection → Documents → Find

Truy vấn nâng cao trong shell:
```bash
docker exec -it mongo mongosh -u root -p secret --authenticationDatabase admin
```
```javascript
use hms_logs;
db.access_logs.find({}).sort({ts:-1}).limit(20);
db.notification_events.find({ type: /appointment\./ }).sort({ts:-1}).limit(20);
```

Export một collection:
```bash
docker exec -it mongo bash -lc "mongoexport --uri='mongodb://root:secret@localhost:27017/hms_logs?authSource=admin' \
  --collection=access_logs --out=/tmp/access_logs.json"
docker cp mongo:/tmp/access_logs.json ./access_logs.json
```

Nếu cần reset toàn bộ (mất dữ liệu log):
```bash
docker compose down -v mongo mongo-express
docker volume rm hospital-management_mongo_data || true
docker compose up -d mongo mongo-express
```

Lý do chọn MongoDB cho logging tạm thời:
- Linh hoạt schema (dễ thêm trường mới không cần migration)
- Tách biệt khỏi luồng giao dịch chính (Postgres)
- Dễ truy vấn ad-hoc qua UI mongo-express

## 6.3 Email Notification (Demo)
Mục tiêu: chứng minh luồng sự kiện → tiêu thụ → gửi thông báo mà không tích hợp nhà cung cấp thật.

Thành phần:
- `notification-service`: consume events từ RabbitMQ và gửi email demo.
- `mailhog`: SMTP giả lập (cổng 1025) + giao diện web http://localhost:8025 hiển thị email.

Luồng:
1. Service nghiệp vụ (appointment / prescription) phát sự kiện (`*.created`, `*.statusUpdated`).
2. RabbitMQ route vào queues `notification_appointment_q`, `notification_prescription_q`.
3. `notification-service` handler ghi log Mongo (`notification_events`) + gửi email (nếu `ENABLE_EMAIL=true`).
4. Mailhog bắt email, xem ở UI.

Biến môi trường chính:
```
ENABLE_EMAIL=true
SMTP_HOST=mailhog
SMTP_PORT=1025
SMTP_FROM="Hospital HMS <noreply@hms.local>"
NOTIFY_DEMO_TO=demo@hms.local
```

Test nhanh:
```
curl -X POST http://localhost:3003/api/appointments \
  -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"patientId":"P1","doctorId":"D1","startTime":"2025-09-08T09:00:00Z","endTime":"2025-09-08T09:30:00Z"}'
# Mở http://localhost:8025 và kiểm tra email "Appointment Created"
```

Giới hạn hiện tại:
- Không retry/backoff riêng (thất bại sẽ thấy trong logs).
- Nội dung email HTML đơn giản, chưa i18n, chưa template engine.
- Chỉ gửi tới một địa chỉ demo tĩnh (`NOTIFY_DEMO_TO`).

Hướng phát triển:
- Thêm hàng đợi nội bộ cho email + retry.
- Template hoá (Handlebars) + đa ngôn ngữ.
- Tích hợp SMTP thực (SES / SendGrid / Mailgun) + cấu hình secrets.

Chạy lệnh sau:
```bash
docker compose exec appointment-service npm run prisma:push
# hoặc
docker compose exec appointment-service npx prisma db push
```

## 7. Luồng nghiệp vụ chính
1. ADMIN đăng ký / đăng nhập user (login lấy JWT).
2. Tạo Patient.
3. Tạo Appointment (kiểm tra không trùng lịch).
4. Cập nhật / hủy Appointment (status).
5. Tạo Prescription (kèm items).
6. Cập nhật Prescription status (ISSUED → PENDING → (DISPENSED | COMPLETED | CANCELED)).
7. Notification-service nhận & log event:
   - appointment.created / statusUpdated / deleted
   - prescription.created / statusUpdated
8. Appointment-service:
  - Tạo lịch (mặc định SCHEDULED)
  - Xác nhận lịch: PATCH /api/appointments/:id/status { "status": "CONFIRMED" }
  - Hoàn tất: PATCH /api/appointments/:id/status { "status": "COMPLETED" }
  - Hủy: PATCH /api/appointments/:id/status { "status": "CANCELED" }

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
- PATCH /:id/status { status } 
  - Hợp lệ: SCHEDULED | CONFIRMED | COMPLETED | CANCELED
  - Quy tắc chuyển:
    - SCHEDULED -> CONFIRMED | CANCELED
    - CONFIRMED -> COMPLETED | CANCELED
    - COMPLETED, CANCELED -> (không chuyển tiếp)
- DELETE /:id

Prescription-service (http://localhost:3005/api/prescriptions):
- POST / { patientId, doctorId, appointmentId?, note?, items[] }
  items: [{ drugName,dosage,frequency,durationDays,instruction? }]
- GET /?patientId=...
- GET /:id
- PATCH /:id/status { status } (ISSUED | PENDING | DISPENSED | COMPLETED | CANCELED)
  Quy tắc & phân quyền tóm tắt:
  - ISSUED -> PENDING | DISPENSED | COMPLETED | CANCELED
  - PENDING -> DISPENSED | COMPLETED | CANCELED
  - DISPENSED -> COMPLETED | CANCELED
  - Nurse chỉ được đặt DISPENSED từ ISSUED hoặc PENDING (ghi audit dispensedBy/dispensedAt)
  - Doctor không được đặt DISPENSED (tách biệt thao tác phát thuốc); vẫn có thể chuyển sang PENDING / COMPLETED / CANCELED
  - Admin không bị hạn chế

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
- Appointment: kiểm tra thời gian + overlap, và quy tắc chuyển trạng thái:
  - SCHEDULED -> CONFIRMED | CANCELED
  - CONFIRMED -> COMPLETED | CANCELED
- Prescription: items bắt buộc. Status transitions:
  - ISSUED -> PENDING | DISPENSED | COMPLETED | CANCELED
  - PENDING -> DISPENSED | COMPLETED | CANCELED
  - DISPENSED -> COMPLETED | CANCELED
  Audit: Khi chuyển sang DISPENSED hệ thống ghi dispensedBy, dispensedAt.
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
1) Đăng nhập (role hợp lệ)
2) Tạo lịch: POST /api/appointments {...} -> SCHEDULED
3) (Tuỳ quy trình) Xác nhận lịch: PATCH /:id/status { "status": "CONFIRMED" }
4) (Tuỳ chọn) Xem danh sách lịch

### Luồng 4: Hoàn tất lịch & lập đơn thuốc
1) Bác sĩ đăng nhập
2) (Khuyên dùng) Nếu đang SCHEDULED: xác nhận trước -> PATCH { "status": "CONFIRMED" }
3) Hoàn tất: PATCH { "status": "COMPLETED" }
4) Tạo đơn thuốc: POST /api/prescriptions {...}
5) Xem chi tiết đơn (tuỳ chọn)  
   GET /api/prescriptions/:id

### Luồng 5: Huỷ lịch khám
1. Đăng nhập (RECEPTIONIST / ADMIN / DOCTOR nếu cho phép)
2. PATCH /api/appointments/:id/status { status:"CANCELED" }

### Luồng 6: Cấp phát đơn thuốc (Nurse / Admin)
1. Nurse hoặc ADMIN đăng nhập (Doctor KHÔNG trực tiếp đặt trạng thái DISPENSED)
2. (Tuỳ chọn) Nếu cần bước chuẩn bị: Doctor/ Admin có thể chuyển ISSUED -> PENDING
3. Nurse/Admin chuyển ISSUED hoặc PENDING -> DISPENSED
4. Hệ thống ghi audit: dispensedBy, dispensedAt
5. (Tuỳ chọn) Sau khi bệnh nhân nhận thuốc và kết thúc quy trình: DISPENSED -> COMPLETED (Admin / Doctor / Nurse nếu cần chính sách mở rộng; hiện tại Admin/Doctor cho phép, Nurse chỉ thao tác DISPENSED)

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
- Appointment: SCHEDULED -> CONFIRMED | CANCELED; CONFIRMED -> COMPLETED | CANCELED
- Prescription: ISSUED -> PENDING | DISPENSED | COMPLETED | CANCELED; PENDING -> DISPENSED | COMPLETED | CANCELED; DISPENSED -> COMPLETED | CANCELED

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

### 17.3 Update Prescription Status (ISSUED -> DISPENSED)
```mermaid
sequenceDiagram
  participant FE
  participant PS as Prescription-Service
  participant MQ as RabbitMQ
  participant NS as Notification-Service

  FE->>PS: PATCH /prescriptions/:id/status {DISPENSED}
  PS->>PS: Validate transition
  PS-->>FE: 200 {status:DISPENSED, dispensedBy, dispensedAt}
  PS->>MQ: prescription.statusUpdated
  MQ-->>NS: event
  NS->>NS: Log
```

--- 
Version: v0 (prototype).
