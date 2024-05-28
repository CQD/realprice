DROP TABLE IF EXISTS types;
CREATE TABLE types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT
);
INSERT INTO types (id, name) VALUES
(0, '住宅大樓'),
(1, '華廈'),
(2, '透天厝'),
(3, '公寓'),
(4, '套房');


DROP TABLE IF EXISTS counties;

CREATE TABLE counties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT
);

DROP TABLE IF EXISTS districts;
CREATE TABLE districts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    county_id INTEGER,
    name TEXT
);

DROP TABLE IF EXISTS house_transactions;
CREATE TABLE house_transactions (
    county_id INTEGER,
    district_id INTEGER,
    transaction_date INTEGER,
    type_id INTEGER,
    age_day INTEGER,
    area REAL,
    price INTEGER,
    parking_area REAL,
    parking_price INTEGER
);


CREATE INDEX index_name
ON house_transactions(district_id);
