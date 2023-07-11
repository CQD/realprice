DROP TABLE IF EXISTS house_transactions;

CREATE TABLE house_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    county TEXT,
    district TEXT,
    transaction_date INTEGER,
    type TEXT,
    build_date INTEGER,
    area REAL,
    price INTEGER,
    parking_area REAL
);