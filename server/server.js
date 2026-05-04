const mysql = require("mysql2");

const db = mysql.createConnection({
  host: process.env.DB_HOST,
  user: process.env.DB_USER,
  password: process.env.DB_PASS,
  database: process.env.DB_NAME,
  port: process.env.DB_PORT
});

app.get("/patients", (req, res) => {
  db.query("SELECT * FROM patients", (err, result) => {
    if (err) {
      return res.status(500).json(err);
    }
    res.json(result);
  });
});

db.connect((err) => {
  if (err) {
    console.error("❌ Database connection failed:", err);
  } else {
    console.log("✅ Connected to Railway MySQL");
  }
});
