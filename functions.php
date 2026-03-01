<?php
require_once __DIR__ . '/db.php';

function money(float $n): string { return '₱' . number_format(abs($n), 2); }

function getAccounts(): array {
    $db = getDB(); $r = $db->query("SELECT * FROM accounts ORDER BY sort_order,name");
    $a = []; while ($row = $r->fetchArray(SQLITE3_ASSOC)) $a[] = $row; return $a;
}
function getTotalBalance(): float { return (float)getDB()->querySingle("SELECT COALESCE(SUM(balance),0) FROM accounts"); }

function addAccount(string $name, string $icon, float $bal): void {
    $db = getDB(); $s = $db->prepare("INSERT INTO accounts (name,icon,balance) VALUES (:n,:i,:b)");
    $s->bindValue(':n',trim($name),SQLITE3_TEXT); $s->bindValue(':i',$icon?:'🏦',SQLITE3_TEXT); $s->bindValue(':b',$bal,SQLITE3_FLOAT); $s->execute();
}
function deleteAccount(int $id): void { $s = getDB()->prepare("DELETE FROM accounts WHERE id=:i"); $s->bindValue(':i',$id,SQLITE3_INTEGER); $s->execute(); }

function updateAccountBalance(int $id, float $delta): void {
    $s = getDB()->prepare("UPDATE accounts SET balance=balance+:d WHERE id=:i");
    $s->bindValue(':d',$delta,SQLITE3_FLOAT); $s->bindValue(':i',$id,SQLITE3_INTEGER); $s->execute();
}

function getCategories(?string $type=null): array {
    $db = getDB(); $sql = "SELECT * FROM categories"; if ($type) $sql .= " WHERE type=:t"; $sql .= " ORDER BY type DESC,name";
    $s = $db->prepare($sql); if ($type) $s->bindValue(':t',$type,SQLITE3_TEXT);
    $r = $s->execute(); $a = []; while ($row = $r->fetchArray(SQLITE3_ASSOC)) $a[] = $row; return $a;
}
function addCategory(string $name, string $type, string $icon): void {
    $s = getDB()->prepare("INSERT INTO categories (name,type,icon) VALUES (:n,:t,:i)");
    $s->bindValue(':n',trim($name),SQLITE3_TEXT); $s->bindValue(':t',$type,SQLITE3_TEXT); $s->bindValue(':i',$icon?:'📌',SQLITE3_TEXT); $s->execute();
}
function deleteCategory(int $id): void { $s = getDB()->prepare("DELETE FROM categories WHERE id=:i"); $s->bindValue(':i',$id,SQLITE3_INTEGER); $s->execute(); }

function getBudgetsWithSpent(): array {
    $db = getDB(); $m = date('Y-m').'%';
    $s = $db->prepare("SELECT b.id, b.amount as budget, c.id as cat_id, c.name, c.icon, COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.category_id=c.id AND t.type='expense' AND t.date LIKE :m),0) as spent FROM budgets b JOIN categories c ON b.category_id=c.id ORDER BY c.name");
    $s->bindValue(':m',$m,SQLITE3_TEXT); $r = $s->execute(); $a = []; while ($row = $r->fetchArray(SQLITE3_ASSOC)) $a[] = $row; return $a;
}
function saveBudget(int $catId, float $amount): void {
    $db = getDB(); $chk = $db->prepare("SELECT id FROM budgets WHERE category_id=:c"); $chk->bindValue(':c',$catId,SQLITE3_INTEGER);
    $ex = $chk->execute()->fetchArray(SQLITE3_ASSOC);
    if ($ex) { $s = $db->prepare("UPDATE budgets SET amount=:a WHERE category_id=:c"); }
    else { $s = $db->prepare("INSERT INTO budgets (amount,category_id) VALUES (:a,:c)"); }
    $s->bindValue(':a',$amount,SQLITE3_FLOAT); $s->bindValue(':c',$catId,SQLITE3_INTEGER); $s->execute();
}
function deleteBudget(int $id): void { $s = getDB()->prepare("DELETE FROM budgets WHERE id=:i"); $s->bindValue(':i',$id,SQLITE3_INTEGER); $s->execute(); }

function getTransactions(int $limit=50): array {
    $s = getDB()->prepare("SELECT t.*, c.name as cat_name, c.icon as cat_icon, a.name as acc_name FROM transactions t LEFT JOIN categories c ON t.category_id=c.id LEFT JOIN accounts a ON t.account_id=a.id ORDER BY t.date DESC, t.created_at DESC LIMIT :l");
    $s->bindValue(':l',$limit,SQLITE3_INTEGER); $r = $s->execute(); $a = []; while ($row = $r->fetchArray(SQLITE3_ASSOC)) $a[] = $row; return $a;
}
function addTransaction(float $amt, string $type, ?int $catId, ?int $accId, ?string $note, string $date, ?string $receipt): void {
    $db = getDB(); $s = $db->prepare("INSERT INTO transactions (amount,type,category_id,account_id,note,date,receipt,created_at) VALUES (:a,:t,:c,:ac,:n,:d,:r,:ts)");
    $s->bindValue(':a',$amt,SQLITE3_FLOAT); $s->bindValue(':t',$type,SQLITE3_TEXT);
    $s->bindValue(':c',$catId,$catId?SQLITE3_INTEGER:SQLITE3_NULL);
    $s->bindValue(':ac',$accId,$accId?SQLITE3_INTEGER:SQLITE3_NULL);
    $s->bindValue(':n',$note,SQLITE3_TEXT); $s->bindValue(':d',$date,SQLITE3_TEXT);
    $s->bindValue(':r',$receipt,SQLITE3_TEXT); $s->bindValue(':ts',date('Y-m-d H:i:s'),SQLITE3_TEXT); $s->execute();
    if ($accId) updateAccountBalance($accId, $type==='income' ? $amt : -$amt);
}
function deleteTransaction(int $id): void {
    $db = getDB(); $s = $db->prepare("SELECT * FROM transactions WHERE id=:i"); $s->bindValue(':i',$id,SQLITE3_INTEGER);
    $t = $s->execute()->fetchArray(SQLITE3_ASSOC); if (!$t) return;
    if ($t['account_id']) updateAccountBalance((int)$t['account_id'], $t['type']==='income' ? -$t['amount'] : $t['amount']);
    if ($t['receipt'] && file_exists(__DIR__.'/uploads/'.$t['receipt'])) unlink(__DIR__.'/uploads/'.$t['receipt']);
    $s2 = $db->prepare("DELETE FROM transactions WHERE id=:i"); $s2->bindValue(':i',$id,SQLITE3_INTEGER); $s2->execute();
}

function getGoals(): array { $r = getDB()->query("SELECT * FROM goals ORDER BY created_at DESC"); $a = []; while ($row = $r->fetchArray(SQLITE3_ASSOC)) $a[] = $row; return $a; }
function saveGoal(string $name, float $target, float $current, ?int $id=null): void {
    $db = getDB();
    if ($id) { $s = $db->prepare("UPDATE goals SET name=:n,target=:t,current=:c WHERE id=:i"); $s->bindValue(':i',$id,SQLITE3_INTEGER); }
    else { $s = $db->prepare("INSERT INTO goals (name,target,current,created_at) VALUES (:n,:t,:c,:ts)"); $s->bindValue(':ts',date('Y-m-d H:i:s'),SQLITE3_TEXT); }
    $s->bindValue(':n',trim($name),SQLITE3_TEXT); $s->bindValue(':t',$target,SQLITE3_FLOAT); $s->bindValue(':c',$current,SQLITE3_FLOAT); $s->execute();
}
function deleteGoal(int $id): void { $s = getDB()->prepare("DELETE FROM goals WHERE id=:i"); $s->bindValue(':i',$id,SQLITE3_INTEGER); $s->execute(); }

function getMonthSummary(): array {
    $s = getDB()->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) as income, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) as expense FROM transactions WHERE date LIKE :m");
    $s->bindValue(':m',date('Y-m').'%',SQLITE3_TEXT); return $s->execute()->fetchArray(SQLITE3_ASSOC) ?: ['income'=>0,'expense'=>0];
}
function getSpendingByCategory(): array {
    $s = getDB()->prepare("SELECT c.name,c.icon,SUM(t.amount) as total FROM transactions t JOIN categories c ON t.category_id=c.id WHERE t.type='expense' AND t.date LIKE :m GROUP BY c.id ORDER BY total DESC");
    $s->bindValue(':m',date('Y-m').'%',SQLITE3_TEXT); $r = $s->execute(); $a = []; while ($row = $r->fetchArray(SQLITE3_ASSOC)) $a[] = $row; return $a;
}
function getLast6Months(): array {
    $db = getDB(); $rows = [];
    for ($i=5;$i>=0;$i--) { $m = date('Y-m',strtotime("-{$i} months"));
        $s = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) as income, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) as expense FROM transactions WHERE date LIKE :m");
        $s->bindValue(':m',$m.'%',SQLITE3_TEXT); $r = $s->execute()->fetchArray(SQLITE3_ASSOC);
        $rows[] = ['month'=>date('M',strtotime($m.'-01')),'income'=>(float)$r['income'],'expense'=>(float)$r['expense']];
    } return $rows;
}
function handleReceipt(): ?string {
    if (!isset($_FILES['receipt'])||$_FILES['receipt']['error']!==UPLOAD_ERR_OK) return null;
    $f=$_FILES['receipt']; if ($f['size']>5*1024*1024) return null;
    $fi=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($fi,$f['tmp_name']); finfo_close($fi);
    if (!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'])) return null;
    $nm=uniqid('r_').'.'.strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    move_uploaded_file($f['tmp_name'],__DIR__.'/uploads/'.$nm); return $nm;
}
function exportCSV(): void {
    $r=getDB()->query("SELECT t.date,t.type,t.amount,c.name as category,a.name as account,t.note FROM transactions t LEFT JOIN categories c ON t.category_id=c.id LEFT JOIN accounts a ON t.account_id=a.id ORDER BY t.date DESC");
    header('Content-Type:text/csv'); header('Content-Disposition:attachment;filename="budget_'.date('Y-m-d').'.csv"');
    $fp=fopen('php://output','w'); fputcsv($fp,['Date','Type','Amount','Category','Account','Note']);
    while ($row=$r->fetchArray(SQLITE3_ASSOC)) fputcsv($fp,$row); fclose($fp); exit;
}
