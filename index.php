<?php
session_start();
define('APP_PASSWORD', password_hash('KatieBruha_02', PASSWORD_DEFAULT));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__pw'])) {
    if (password_verify($_POST['__pw'], APP_PASSWORD)) {
        $_SESSION['auth'] = true;
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
}

if (empty($_SESSION['auth'])):
?>


<?php
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    match($a) {
        'add_txn' => addTransaction((float)$_POST['amount'], $_POST['type'],
            $_POST['category_id']?(int)$_POST['category_id']:null,
            $_POST['account_id']?(int)$_POST['account_id']:null,
            $_POST['note']??'', $_POST['date']?:date('Y-m-d'), handleReceipt()),
        'delete_txn'     => deleteTransaction((int)$_POST['id']),
        'add_account'    => addAccount($_POST['name'], $_POST['icon']??'🏦', (float)($_POST['balance']??0)),
        'delete_account' => deleteAccount((int)$_POST['id']),
        'add_category'   => addCategory($_POST['name'], $_POST['type'], $_POST['icon']??'📌'),
        'delete_category'=> deleteCategory((int)$_POST['id']),
        'save_budget'    => saveBudget((int)$_POST['category_id'], (float)$_POST['amount']),
        'delete_budget'  => deleteBudget((int)$_POST['id']),
        'save_goal'      => saveGoal($_POST['name'], (float)$_POST['target'], (float)($_POST['current']??0), $_POST['goal_id']?(int)$_POST['goal_id']:null),
        'delete_goal'    => deleteGoal((int)$_POST['id']),
        'export_csv'     => exportCSV(),
        default => null,
    };
    header('Location: '.strtok($_SERVER['REQUEST_URI'],'?')); exit;
}

$bal = getTotalBalance();
$accs = getAccounts();
$mo = getMonthSummary();
$budgets = getBudgetsWithSpent();
$txns = getTransactions(50);
$cats = getCategories();
$goals = getGoals();
$catSpend = getSpendingByCategory();
$six = getLast6Months();
$eCats = array_filter($cats, fn($c)=>$c['type']==='expense');
$iCats = array_filter($cats, fn($c)=>$c['type']==='income');
$tBud = array_sum(array_column($budgets,'budget'));
$tSp = array_sum(array_column($budgets,'spent'));
$tLeft = $tBud - $tSp;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no">
<meta name="theme-color" content="#F5F5F7">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Budget</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div id="app">

<!-- ══ TAB: HOME ══ -->
<div class="tab active" id="tab-home">

<div class="hero">
    <div class="hero-sub"><?= date('F Y') ?></div>
    <div class="hero-bal mono <?= $bal<0?'red':'' ?>"><?= ($bal<0?'-':'').money($bal) ?></div>
    <div class="hero-pills">
        <div class="hp hp-g"><span class="mono"><?= money($mo['income']) ?></span><span class="hp-l">in</span></div>
        <div class="hp hp-r"><span class="mono"><?= money($mo['expense']) ?></span><span class="hp-l">out</span></div>
    </div>
</div>

<div class="s">
    <div class="sh"><h2>Accounts</h2><button class="lk" onclick="openSheet('shAcc')">+ Add</button></div>
    <div class="acc-row">
    <?php foreach ($accs as $ac): ?>
        <div class="acc"><?= $ac['icon'] ?><span class="acc-n"><?= htmlspecialchars($ac['name']) ?></span><span class="acc-b mono <?= $ac['balance']<0?'red':'' ?>"><?= ($ac['balance']<0?'-':'').money($ac['balance']) ?></span></div>
    <?php endforeach; ?>
    <?php if (!$accs): ?><p class="mt">No accounts yet</p><?php endif; ?>
    </div>
</div>

<?php if ($budgets): ?>
<div class="s">
    <div class="sh"><h2>Budget Left</h2><span class="pill <?= $tLeft<0?'pill-r':'pill-g' ?>"><?= $tLeft<0?'-':'' ?><?= money($tLeft) ?></span></div>
    <?php foreach ($budgets as $b):
        $left = $b['budget']-$b['spent']; $pct = $b['budget']>0 ? min(100,round(($b['spent']/$b['budget'])*100)) : 0;
        $cls = $pct>=100?'over':($pct>=75?'warn':'ok');
    ?>
    <div class="bud">
        <div class="bud-h"><span><?= $b['icon'] ?> <?= htmlspecialchars($b['name']) ?></span><span class="mono <?= $left<=0?'red':'' ?>"><?= $left<0?'-'.money($left):money($left) ?> left</span></div>
        <div class="prog"><div class="pf pf-<?= $cls ?>" style="width:<?= $pct ?>%"></div></div>
        <div class="bud-f"><span class="dim"><?= money($b['spent']) ?> / <?= money($b['budget']) ?></span><span class="dim"><?= $pct ?>%</span></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($goals): ?>
<div class="s">
    <div class="sh"><h2>Goals</h2></div>
    <?php foreach ($goals as $g): $pct = $g['target']>0?min(100,round(($g['current']/$g['target'])*100)):0; ?>
    <div class="bud">
        <div class="bud-h"><span>🎯 <?= htmlspecialchars($g['name']) ?></span><span class="mono accent"><?= $pct ?>%</span></div>
        <div class="prog"><div class="pf pf-a" style="width:<?= $pct ?>%"></div></div>
        <div class="bud-f">
            <span class="dim"><?= money($g['current']) ?> / <?= money($g['target']) ?></span>
            <form method="post" class="ir"><input type="hidden" name="action" value="save_goal"><input type="hidden" name="goal_id" value="<?= $g['id'] ?>"><input type="hidden" name="name" value="<?= htmlspecialchars($g['name']) ?>"><input type="hidden" name="target" value="<?= $g['target'] ?>"><input type="number" name="current" value="<?= $g['current'] ?>" step="0.01" class="mi" inputmode="decimal"><button type="submit" class="sb">Save</button></form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="s">
    <div class="sh"><h2>Recent</h2><form method="post" class="ir"><input type="hidden" name="action" value="export_csv"><button type="submit" class="lk">CSV ↓</button></form></div>
    <?php if (!$txns): ?><p class="mt">No transactions yet</p>
    <?php else: ?>
    <ul class="tl"><?php $ld=''; foreach (array_slice($txns,0,20) as $t):
        $d = date('M j',strtotime($t['date']));
        if ($d!==$ld): $ld=$d; ?><li class="ds"><?= $d===date('M j')?'Today':($d===date('M j',strtotime('-1 day'))?'Yesterday':$d) ?></li><?php endif; ?>
        <li class="tx">
            <div class="tx-i"><?= $t['cat_icon']??'📌' ?></div>
            <div class="tx-m"><div class="tx-c"><?= htmlspecialchars($t['cat_name']??'Uncategorized') ?></div><div class="tx-s"><?= htmlspecialchars($t['acc_name']??'') ?><?= $t['note']?' · '.htmlspecialchars($t['note']):'' ?><?php if($t['receipt']): ?> <span class="rcp" onclick="event.stopPropagation();showR('uploads/<?= $t['receipt'] ?>')">📎</span><?php endif; ?></div></div>
            <div class="tx-r"><span class="tx-a mono <?= $t['type'] ?>"><?= $t['type']==='income'?'+':'-' ?><?= money($t['amount']) ?></span><form method="post" class="ir" onsubmit="event.stopPropagation();return confirm('Delete?')"><input type="hidden" name="action" value="delete_txn"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button type="submit" class="xb">×</button></form></div>
        </li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
</div>
</div>

<!-- ══ TAB: ADD ══ -->
<div class="tab" id="tab-add">
<div class="s">
    <h2 class="pt">New Transaction</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_txn">
        <input type="hidden" name="type" id="txnType" value="expense">
        <div class="tog"><button type="button" class="tb active" data-t="expense">Expense</button><button type="button" class="tb" data-t="income">Income</button></div>
        <div class="aw"><span class="cur">₱</span><input type="number" name="amount" step="0.01" min="0.01" placeholder="0" required inputmode="decimal" class="ai" id="amtIn"></div>
        <div class="f2">
            <select name="account_id" class="inp"><option value="">Account</option><?php foreach ($accs as $ac): ?><option value="<?= $ac['id'] ?>"><?= $ac['icon'] ?> <?= htmlspecialchars($ac['name']) ?></option><?php endforeach; ?></select>
            <select name="category_id" id="catSel" class="inp"><option value="">Category</option><?php foreach ($eCats as $c): ?><option value="<?= $c['id'] ?>" data-t="expense"><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?><?php foreach ($iCats as $c): ?><option value="<?= $c['id'] ?>" data-t="income" class="io"><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
        </div>
        <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="inp">
        <input type="text" name="note" placeholder="Note (optional)" class="inp">
        <div class="f2"><label class="fbtn">📎 Receipt<input type="file" name="receipt" accept="image/*" capture="environment" hidden></label><button type="submit" class="btn bp">Add Transaction</button></div>
    </form>
</div>
</div>

<!-- ══ TAB: REPORTS ══ -->
<div class="tab" id="tab-reports">
<div class="s">
    <h2 class="pt">Reports — <?= date('F Y') ?></h2>
    <div class="sg">
        <div class="sc"><div class="sl">Income</div><div class="sv green mono"><?= money($mo['income']) ?></div></div>
        <div class="sc"><div class="sl">Expenses</div><div class="sv red mono"><?= money($mo['expense']) ?></div></div>
        <div class="sc"><div class="sl">Net</div><div class="sv mono <?= ($mo['income']-$mo['expense'])<0?'red':'green' ?>"><?= ($mo['income']-$mo['expense'])<0?'-':'+' ?><?= money($mo['income']-$mo['expense']) ?></div></div>
        <div class="sc"><div class="sl">Budget Left</div><div class="sv mono <?= $tLeft<0?'red':'' ?>"><?= $tLeft<0?'-':'' ?><?= money($tLeft) ?></div></div>
    </div>
    <?php if ($catSpend): $mx=max(array_column($catSpend,'total')); ?>
    <div class="ss"><h3>Spending by Category</h3>
    <?php foreach ($catSpend as $cs): $w=$mx>0?round(($cs['total']/$mx)*100):0; ?>
    <div class="br"><span class="bl"><?= $cs['icon'] ?> <?= htmlspecialchars($cs['name']) ?></span><div class="bt"><div class="bf" style="width:<?= $w ?>%"></div></div><span class="bv mono"><?= money($cs['total']) ?></span></div>
    <?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($six): $mt=max(array_merge(array_column($six,'income'),array_column($six,'expense')))?:1; ?>
    <div class="ss"><h3>6-Month Trend</h3>
    <div class="tc"><?php foreach ($six as $sm): $ih=round(($sm['income']/$mt)*100); $eh=round(($sm['expense']/$mt)*100); ?>
    <div class="tcol"><div class="tbs"><div class="tbr gb" style="height:<?= $ih ?>%"></div><div class="tbr rb" style="height:<?= $eh ?>%"></div></div><span class="tl"><?= $sm['month'] ?></span></div>
    <?php endforeach; ?></div>
    <div class="tleg"><span><i class="ld gb"></i>Income</span><span><i class="ld rb"></i>Expense</span></div>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- ══ TAB: MANAGE ══ -->
<div class="tab" id="tab-manage">
<div class="s">
    <h2 class="pt">Manage</h2>

    <div class="ss"><h3>Set Budgets</h3>
    <form method="post" class="f2" style="margin-bottom:10px"><input type="hidden" name="action" value="save_budget">
        <select name="category_id" class="inp" required><option value="">Category</option><?php foreach ($eCats as $c): ?><option value="<?= $c['id'] ?>"><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
        <div class="ir" style="flex:1;gap:6px"><input type="number" name="amount" placeholder="Amount" step="0.01" min="1" required class="inp" inputmode="decimal" style="margin:0"><button type="submit" class="sb">Set</button></div>
    </form>
    <?php foreach ($budgets as $b): ?><div class="mr"><span><?= $b['icon'] ?> <?= htmlspecialchars($b['name']) ?> — <span class="mono"><?= money($b['budget']) ?></span>/mo</span><form method="post" class="ir" onsubmit="return confirm('Remove?')"><input type="hidden" name="action" value="delete_budget"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button type="submit" class="xb">×</button></form></div><?php endforeach; ?>
    </div>

    <div class="ss"><h3>Goals</h3>
    <form method="post"><input type="hidden" name="action" value="save_goal"><input type="hidden" name="goal_id" value=""><input type="hidden" name="current" value="0">
        <input type="text" name="name" placeholder="Goal name" required class="inp">
        <div class="f2"><input type="number" name="target" placeholder="Target" step="0.01" min="1" required class="inp" inputmode="decimal"><button type="submit" class="btn ba">Add Goal</button></div>
    </form>
    <?php foreach ($goals as $g): ?><div class="mr"><span>🎯 <?= htmlspecialchars($g['name']) ?> — <span class="mono"><?= money($g['target']) ?></span></span><form method="post" class="ir" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_goal"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button type="submit" class="xb">×</button></form></div><?php endforeach; ?>
    </div>

    <div class="ss"><h3>Accounts</h3>
    <form method="post"><input type="hidden" name="action" value="add_account">
        <div class="f3"><input type="text" name="icon" placeholder="🏦" maxlength="4" class="inp ic"><input type="text" name="name" placeholder="Name" required class="inp"><input type="number" name="balance" placeholder="Balance" step="0.01" class="inp" inputmode="decimal"></div>
        <button type="submit" class="btn bo" style="margin-top:6px">Add Account</button>
    </form>
    <?php foreach ($accs as $ac): ?><div class="mr"><span><?= $ac['icon'] ?> <?= htmlspecialchars($ac['name']) ?> — <span class="mono"><?= money($ac['balance']) ?></span></span><form method="post" class="ir" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_account"><input type="hidden" name="id" value="<?= $ac['id'] ?>"><button type="submit" class="xb">×</button></form></div><?php endforeach; ?>
    </div>

    <div class="ss"><h3>Categories</h3>
    <form method="post"><input type="hidden" name="action" value="add_category">
        <div class="f3"><input type="text" name="icon" placeholder="📌" maxlength="4" class="inp ic"><input type="text" name="name" placeholder="Name" required class="inp"><select name="type" class="inp"><option value="expense">Expense</option><option value="income">Income</option></select></div>
        <button type="submit" class="btn bo" style="margin-top:6px">Add Category</button>
    </form>
    <?php foreach ($cats as $c): ?><div class="mr"><span><?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?> <span class="tg tg-<?= $c['type'] ?>"><?= $c['type'] ?></span></span><form method="post" class="ir" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button type="submit" class="xb">×</button></form></div><?php endforeach; ?>
    </div>
</div>
</div>

<div class="ov" id="rov" onclick="this.classList.remove('show')"><img id="rimg" src="" alt=""></div>

<div class="bk" id="shAcc-bk" onclick="closeSheet('shAcc')"></div>
<div class="sheet" id="shAcc"><div class="shh"></div><h3>Add Account</h3>
<form method="post"><input type="hidden" name="action" value="add_account">
<div class="f3"><input type="text" name="icon" placeholder="🏦" maxlength="4" class="inp ic"><input type="text" name="name" placeholder="Name" required class="inp"><input type="number" name="balance" placeholder="0" step="0.01" class="inp" inputmode="decimal"></div>
<button type="submit" class="btn bp" style="margin-top:10px">Add Account</button></form></div>

<nav class="nav">
    <button class="nb active" data-tab="home"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7"/><path d="M9 22V12h6v10"/></svg>Home</button>
    <button class="nb" data-tab="add"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>Add</button>
    <button class="nb" data-tab="reports"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Reports</button>
    <button class="nb" data-tab="manage"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Manage</button>
</nav>
</div>
<script src="script.js"></script>
</body>
</html>
