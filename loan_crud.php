<?php
require 'db.php';
 
function clean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)));
}
 
/* AUTO-CLASSIFY STATUS BASED ON DUE DATE
   due_date < today         = Overdue
   due_date <= today + 30   = Critical
   due_date > today + 30    = Pending
*/
function autoStatus(string $due_date): string {
    if (empty($due_date)) return 'Pending';
    $today = new DateTime('today');
    $due   = new DateTime($due_date);
    $diff  = (int)$today->diff($due)->format('%r%a');
    if ($diff < 0)   return 'Overdue';
    if ($diff <= 30) return 'Critical';
    return 'Pending';
}
 
/* DELETE */
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $s = $conn->prepare("DELETE FROM loan_defaulters WHERE id = ?");
        $s->bind_param('i', $id);
        $s->execute();
        $s->close();
    }
    $conn->close();
    header('Location: index.php?msg=deleted');
    exit;
}
 
/* CREATE / UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action      = $_POST['action'];
    $full_name   = clean($_POST['full_name']   ?? '');
    $account_no  = clean($_POST['account_no']  ?? '');
    $phone       = clean($_POST['phone']        ?? '');
    $email       = clean($_POST['email']        ?? '');
    $loan_amount = (float)($_POST['loan_amount'] ?? 0);
    $amount_due  = (float)($_POST['amount_due']  ?? 0);
    $due_date    = clean($_POST['due_date']      ?? '');
    $loan_type   = clean($_POST['loan_type']     ?? '');
    $notes       = clean($_POST['notes']         ?? '');
    $due_date_val = $due_date ?: null;
    $status      = autoStatus($due_date);
 
    if (empty($full_name) || empty($account_no)) {
        $conn->close();
        header('Location: index.php?form=1&error=' . urlencode('Full name and account number are required.'));
        exit;
    }
 
    if ($action === 'create') {
        $sql  = "INSERT INTO loan_defaulters (full_name, account_no, phone, email, loan_amount, amount_due, due_date, loan_type, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssddssss', $full_name, $account_no, $phone, $email, $loan_amount, $amount_due, $due_date_val, $loan_type, $status, $notes);
        $ok = $stmt->execute();
        $stmt->close(); $conn->close();
        header('Location: index.php?msg=' . ($ok ? 'created' : 'error'));
        exit;
    }
 
    if ($action === 'update') {
        $id  = (int)($_POST['defaulter_id'] ?? 0);
        $sql = "UPDATE loan_defaulters SET full_name=?, account_no=?, phone=?, email=?, loan_amount=?, amount_due=?, due_date=?, loan_type=?, status=?, notes=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssddssssi', $full_name, $account_no, $phone, $email, $loan_amount, $amount_due, $due_date_val, $loan_type, $status, $notes, $id);
        $ok = $stmt->execute();
        $stmt->close(); $conn->close();
        header('Location: index.php?msg=' . ($ok ? 'updated' : 'error'));
        exit;
    }
}
 
$conn->close();
header('Location: index.php');
exit;