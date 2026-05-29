<?php
require_once "inc/db.php";
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'ceo') {
  die("Access denied.");
}
// List pending payouts
$stmt = $pdo->query("
  SELECT e.id, u.name AS instructor, c.title, e.instructor_share, e.created_at
  FROM instructor_earnings e
  JOIN users u ON e.instructor_id = u.id
  JOIN courses c ON e.course_id = c.id
  WHERE e.status = 'pending'
");
$payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
 <?php include 'inc/header.php'; ?>
 <?php include 'inc/sidebar.php'; ?>
 <div class=" main-content" id="mainContent">
<h2>Pending Instructor Payouts</h2>
<table border="1" cellpadding="8">
  <tr>
    <th>Instructor</th>
    <th>Course</th>
    <th>Earning (₦)</th>
    <th>Date</th>
    <th>Action</th>
  </tr>
  <?php foreach ($payouts as $p): ?>
  <tr>
    <td><?= htmlspecialchars($p['instructor']) ?></td>
    <td><?= htmlspecialchars($p['title']) ?></td>
    <td><?= number_format($p['instructor_share'], 2) ?></td>
    <td><?= $p['created_at'] ?></td>
    <td><a href="payout_mark_paid.php?id=<?= $p['id'] ?>">Mark as Paid</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php include 'inc/script.php'; ?>
