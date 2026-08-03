CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE
);

INSERT OR IGNORE INTO users (id, name, email) VALUES (1, 'Nelson', 'nelson@example.com');
INSERT OR IGNORE INTO users (id, name, email) VALUES (2, 'Alice', 'alice@example.com');
