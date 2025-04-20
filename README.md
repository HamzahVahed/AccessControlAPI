# AccessControlAPI
Design of RESTful API for Access Control and Batch Updates
# Centralized Access Control System 

This repository contains the PHP-based APIs and system documentation for the **Internal Access Control System (IACS)**

## 📁 Contents

- `normal_api/insert_log.php`: Receives real-time entry/exit log data from hardware.
- `batch_api/batch_log2.php`: Handles CSV data exports from location-specific logs.
- `report/222003168P2.docx`: Full technical and performance report.

---

## 📌 Project Overview

This system implements a **centralized access control backend** using:
- PHP REST-like APIs
- MySQL database
- Local XAMPP server
- Flutter-based mobile frontend

The APIs are designed to support:
- Real-time logging of student access data
- Batch processing of room logs into CSV
- SQL injection protection (via prepared statements)
- Fast response time (<200ms), tested with Postman and JMeter

---

## 🔧 Technologies Used

- **PHP**
- **MySQL**
- **XAMPP**
- **Flutter (frontend)**
- **Postman** (testing)
- **Apache JMeter** (load testing)

---

## 🔐 Security

All SQL queries are protected using `prepared statements`. APIs are tested for SQL injection vulnerabilities and have passed all validation scenarios.

---

## 🧪 Performance

Tested using:
- 100–1000 concurrent requests via JMeter
- SQL injection simulations via Postman

Results:
- 100% uptime
- 0–0.05% error rate under load
- Response time: 13–24ms average

---

## 🚀 Getting Started

1. Clone this repo
2. Setup XAMPP and create the MySQL schema (as per report)
3. Copy `log_api.php` and `batch_api.php` to your XAMPP `htdocs` folder
4. Test with Postman using example payloads

Example payload (JSON):
```json
{
  "StudentNumber": "222009999",
  "doorID": "Lab1",
  "ActiveStatus": "IN"
}
