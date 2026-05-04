require("dotenv").config();

const express = require("express");
const cors = require("cors");

const app = express();

// Middleware
app.use(cors());
app.use(express.json());

// ✅ IMPORTANT: Use Render's PORT
const PORT = process.env.PORT || 5000;

// ✅ Root test route (VERY IMPORTANT for Render)
app.get("/", (req, res) => {
  res.send("✅ Patient Record System API is running");
});

// ✅ Sample API route
app.get("/api/test", (req, res) => {
  res.json({ message: "API working!" });
});

// ===============================
// 🔌 OPTIONAL: MySQL CONNECTION
// ===============================
/*
const mysql = require("mysql2");

const db = mysql.createConnection({
  host: process.env.DB_HOST,
  user: process.env.DB_USER,
  password: process.env.DB_PASS,
  database: process.env.DB_NAME
});

db.connect((err) => {
  if (err) {
    console.error("❌ DB connection failed:", err);
  } else {
    console.log("✅ Connected to MySQL");
  }
});
*/

// ===============================
// 🚀 START SERVER
// ===============================
app.listen(PORT, () => {
  console.log(`🚀 Server running on port ${PORT}`);
});
