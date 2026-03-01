<?php
function getDB(): SQLite3 {
    $path = __DIR__ . '/budget.sqlite';
    $new = !file_exists($path);
    $db = new SQLite3($path);
    $db->busyTimeout(3000);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');
    if (!$new) return $db;

    $db->exec('CREATE TABLE accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, icon TEXT DEFAULT "🏦", balance REAL DEFAULT 0, sort_order INTEGER DEFAULT 0)');
    $db->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, type TEXT NOT NULL DEFAULT "expense", icon TEXT DEFAULT "📌")');
    $db->exec('CREATE TABLE budgets (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER NOT NULL UNIQUE, amount REAL NOT NULL, FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE)');
    $db->exec('CREATE TABLE transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, amount REAL NOT NULL, type TEXT NOT NULL, category_id INTEGER, account_id INTEGER, note TEXT, date TEXT NOT NULL, receipt TEXT, created_at TEXT, FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL, FOREIGN KEY(account_id) REFERENCES accounts(id) ON DELETE SET NULL)');
    $db->exec('CREATE TABLE goals (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, target REAL NOT NULL, current REAL DEFAULT 0, created_at TEXT)');

    $db->exec("INSERT INTO accounts (name,icon,balance,sort_order) VALUES ('Cash','💵',0,1)");
    $db->exec("INSERT INTO accounts (name,icon,balance,sort_order) VALUES ('BPI','🏦',0,2)");
    $db->exec("INSERT INTO accounts (name,icon,balance,sort_order) VALUES ('GCash','📱',0,3)");

    foreach ([['Salary','income','💰'],['Freelance','income','💻'],['Other Income','income','📥'],['Food','expense','🍜'],['Transport','expense','🚗'],['Shopping','expense','🛍️'],['Bills','expense','⚡'],['Entertainment','expense','🎬'],['Health','expense','💊'],['Groceries','expense','🛒'],['Rent','expense','🏠'],['Other','expense','📎']] as $c) {
        $s = $db->prepare("INSERT INTO categories (name,type,icon) VALUES (:n,:t,:i)");
        $s->bindValue(':n',$c[0],SQLITE3_TEXT); $s->bindValue(':t',$c[1],SQLITE3_TEXT); $s->bindValue(':i',$c[2],SQLITE3_TEXT);
        $s->execute();
    }
    return $db;
}
