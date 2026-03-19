<?php
require 'db.php';
 
/* AUTO-REFRESH STATUS on every page load */
$conn->query("UPDATE loan_defaulters SET status = 'Overdue' WHERE due_date IS NOT NULL AND due_date < CURDATE()");
$conn->query("UPDATE loan_defaulters SET status = 'Critical' WHERE due_date IS NOT NULL AND due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$conn->query("UPDATE loan_defaulters SET status = 'Pending' WHERE due_date IS NOT NULL AND due_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
 
/* ── Pre-load editing record if ?edit=ID ── */
$editing = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM loan_defaulters WHERE id = ?");
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $res  = $stmt->get_result();
    $editing = $res->num_rows > 0 ? $res->fetch_assoc() : null;
    $stmt->close();
}
 
/* ── Pre-load view record if ?view=ID ── */
$viewing = null;
if (isset($_GET['view'])) {
    $vid  = (int)$_GET['view'];
    $stmt = $conn->prepare("SELECT * FROM loan_defaulters WHERE id = ?");
    $stmt->bind_param('i', $vid);
    $stmt->execute();
    $res  = $stmt->get_result();
    $viewing = $res->num_rows > 0 ? $res->fetch_assoc() : null;
    $stmt->close();
}
 
/* ── Fetch all records ── */
$all       = $conn->query("SELECT * FROM loan_defaulters ORDER BY due_date ASC")->fetch_all(MYSQLI_ASSOC);
$total     = count($all);
$total_due = array_sum(array_column($all, 'amount_due'));
$critical  = count(array_filter($all, fn($r) => $r['status'] === 'Critical'));
$overdue   = count(array_filter($all, fn($r) => $r['status'] === 'Overdue'));
$conn->close();
 
function fmt($n) { return 'RWF ' . number_format((float)$n, 0, '.', ','); }
function val(string $k, ?array $e): string {
    if ($e && isset($e[$k])) return htmlspecialchars($e[$k]);
    return '';
}
function sel(string $k, string $opt, ?array $e): string {
    return ($e[$k] ?? '') === $opt ? 'selected' : '';
}
 
/* Which panel is open: form | view | none */
$panel = isset($_GET['form']) || $editing ? 'form' : ($viewing ? 'view' : 'none');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>NovaBank — Loan Recovery System</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --iv:  #fffff0;
      --br:  #341100;
      --brd: #1e0900;
      --brm: #5c2200;
      --brl: #7a3a18;
      --gd:  #c19a6b;
      --fn:  #fdf8f0;
      --bd:  #d4b896;
      --bdd: #a89060;
      --tx:  #1e0900;
      --mt:  #6b4030;
      --bg:  #f5ede2;
    }
 
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--tx); min-height:100vh; display:flex; }
 
    /* ── SIDEBAR ── */
    .sb {
      width: 230px; min-height: 100vh; background: var(--br);
      display: flex; flex-direction: column; flex-shrink: 0;
      box-shadow: 4px 0 20px rgba(0,0,0,0.2);
      position: sticky; top: 0; height: 100vh;
    }
    .sb-brand { padding:1.5rem 1.3rem 1.1rem; border-bottom:1px solid rgba(255,255,240,0.1); }
    .sb-logo  { display:flex; align-items:center; gap:10px; }
    .sb-seal  { width:36px; height:36px; border-radius:50%; border:1.5px solid var(--gd); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .sb-seal svg { width:17px; height:17px; stroke:var(--gd); fill:none; stroke-width:1.8; stroke-linecap:round; }
    .sb-name  { font-family:'Cormorant Garamond',serif; font-size:1.1rem; font-weight:700; color:var(--iv); letter-spacing:0.06em; }
    .sb-name span { color:var(--gd); }
    .sb-sub   { font-size:0.6rem; color:rgba(255,255,240,0.35); letter-spacing:0.14em; text-transform:uppercase; margin-top:2px; }
    .sb-nav   { flex:1; padding:1.1rem 0.7rem; display:flex; flex-direction:column; gap:3px; }
    .nav-lbl  { font-size:0.62rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:rgba(255,255,240,0.22); padding:8px 12px 3px; }
    .nav-item {
      display:flex; align-items:center; gap:9px;
      padding:9px 12px; border-radius:8px;
      font-size:0.82rem; font-weight:500; color:rgba(255,255,240,0.55);
      text-decoration:none; transition:background 0.14s, color 0.14s;
    }
    .nav-item:hover { background:rgba(255,255,240,0.08); color:var(--iv); }
    .nav-item.active { background:rgba(193,154,107,0.2); color:var(--gd); }
    .nav-item svg { width:15px; height:15px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; flex-shrink:0; }
    .sb-foot  { padding:0.9rem; border-top:1px solid rgba(255,255,240,0.1); }
    .sb-info  { font-size:0.72rem; color:rgba(255,255,240,0.35); text-align:center; letter-spacing:0.04em; }
 
    /* ── MAIN ── */
    .main { flex:1; display:flex; flex-direction:column; min-width:0; }
 
    /* ── TOPBAR ── */
    .topbar {
      background:var(--iv); border-bottom:1px solid var(--bd);
      padding:0 1.8rem; height:60px;
      display:flex; align-items:center; justify-content:space-between;
      box-shadow:0 1px 6px rgba(52,17,0,0.07); flex-shrink:0;
      position:sticky; top:0; z-index:50;
    }
    .tb-title { font-family:'Cormorant Garamond',serif; font-size:1.25rem; font-weight:600; color:var(--br); }
    .tb-right { display:flex; align-items:center; gap:10px; }
    .tb-date  { font-size:0.74rem; color:var(--mt); }
    .btn-add-top {
      display:inline-flex; align-items:center; gap:6px;
      background:var(--br); color:var(--iv);
      padding:8px 16px; border-radius:7px;
      font-family:'DM Sans',sans-serif; font-size:0.8rem; font-weight:700;
      text-decoration:none; transition:background 0.14s, transform 0.12s;
      box-shadow:0 2px 8px rgba(52,17,0,0.2);
    }
    .btn-add-top:hover { background:var(--brd); transform:translateY(-1px); }
    .btn-add-top svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2.5; stroke-linecap:round; }
 
    /* ── PAGE BODY ── */
    .pb { padding:1.8rem; flex:1; }
 
    /* ── STATS ── */
    .stats { display:grid; grid-template-columns:repeat(4,1fr); gap:0.9rem; margin-bottom:1.8rem; }
    .sc {
      background:var(--iv); border:1px solid var(--bd); border-radius:13px;
      padding:1.1rem 1.3rem; position:relative; overflow:hidden;
      transition:transform 0.18s, box-shadow 0.18s;
    }
    .sc::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:var(--br); }
    .sc.gd::before { background:var(--gd); }
    .sc.rd::before { background:#c0392b; }
    .sc.md::before { background:var(--brm); }
    .sc:hover { transform:translateY(-2px); box-shadow:0 5px 18px rgba(52,17,0,0.1); }
    .si { width:38px; height:38px; border-radius:9px; display:flex; align-items:center; justify-content:center; margin-bottom:0.8rem; }
    .si svg { width:17px; height:17px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .si.b { background:rgba(52,17,0,0.08); color:var(--br); }
    .si.g { background:rgba(193,154,107,0.15); color:var(--gd); }
    .si.r { background:rgba(192,57,43,0.1); color:#c0392b; }
    .si.m { background:rgba(92,34,0,0.1); color:var(--brm); }
    .sv { font-family:'Cormorant Garamond',serif; font-size:1.6rem; font-weight:700; color:var(--br); line-height:1; }
    .sl { font-size:0.7rem; color:var(--mt); margin-top:3px; font-weight:500; }
 
    /* ── ALERT ── */
    .alert { padding:10px 15px; border-radius:8px; font-size:0.83rem; margin-bottom:1.4rem; font-weight:500; border-left:3px solid; }
    .alert.s { background:rgba(52,17,0,0.06); border-color:var(--br); color:var(--br); }
    .alert.e { background:#fff0ee; border-color:#c0392b; color:#c0392b; }
 
    /* ── CONTENT ROW (table + panel) ── */
    .content-row { display:grid; grid-template-columns:1fr; gap:1.4rem; }
 
    /* ── TABLE CARD ── */
    .table-card { background:var(--iv); border:1px solid var(--bd); border-radius:14px; overflow:hidden; }
    .table-head {
      padding:0.9rem 1.4rem; background:var(--fn); border-bottom:1px solid var(--bd);
      display:flex; align-items:center; justify-content:space-between; gap:0.8rem; flex-wrap:wrap;
    }
    .th-title { font-family:'Cormorant Garamond',serif; font-size:1rem; font-weight:600; color:var(--br); }
    .sw { position:relative; }
    .sw input {
      background:#fff; border:1px solid var(--bd); color:var(--tx); border-radius:7px;
      padding:6px 11px 6px 28px; font-family:'DM Sans',sans-serif; font-size:0.8rem;
      outline:none; width:190px; transition:border-color 0.14s;
    }
    .sw input:focus { border-color:var(--br); }
    .sw svg { position:absolute; left:9px; top:50%; transform:translateY(-50%); width:12px; height:12px; stroke:var(--mt); fill:none; stroke-width:2; stroke-linecap:round; pointer-events:none; }
    .tw { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; font-size:0.83rem; }
    thead th {
      text-align:left; padding:8px 12px;
      font-size:0.64rem; font-weight:700; color:var(--br);
      letter-spacing:0.09em; text-transform:uppercase;
      border-bottom:1px solid var(--bd); background:var(--fn);
    }
    tbody tr { border-bottom:1px solid rgba(212,184,150,0.35); transition:background 0.12s; }
    tbody tr:hover { background:rgba(52,17,0,0.025); }
    tbody td { padding:10px 12px; vertical-align:middle; }
    td.mu { color:var(--mt); font-size:0.78rem; }
    .nc { display:flex; align-items:center; gap:9px; }
    .av { width:30px; height:30px; border-radius:50%; background:var(--br); display:flex; align-items:center; justify-content:center; font-family:'Cormorant Garamond',serif; font-size:0.82rem; font-weight:700; color:var(--gd); flex-shrink:0; }
    .nc strong { display:block; font-size:0.82rem; }
    .nc small  { color:var(--mt); font-size:0.72rem; }
    .amt  { font-family:'Cormorant Garamond',serif; font-size:0.94rem; font-weight:600; color:var(--br); }
    .amt.red { color:#c0392b; }
    .badge {
      display:inline-flex; align-items:center; gap:4px;
      padding:2px 9px; border-radius:20px; font-size:0.66rem; font-weight:700; letter-spacing:0.04em;
    }
    .badge::before { content:''; width:5px; height:5px; border-radius:50%; flex-shrink:0; }
    .badge.overdue  { background:rgba(192,57,43,0.1);  color:#c0392b; border:1px solid rgba(192,57,43,0.22); }
    .badge.overdue::before  { background:#c0392b; }
    .badge.critical { background:rgba(52,17,0,0.1);    color:var(--br); border:1px solid rgba(52,17,0,0.22); }
    .badge.critical::before { background:var(--br); }
    .badge.pending  { background:rgba(193,154,107,0.15); color:var(--brm); border:1px solid rgba(193,154,107,0.28); }
    .badge.pending::before  { background:var(--gd); }
    .ac { display:flex; gap:5px; }
    .ba {
      padding:4px 10px; border-radius:5px;
      font-family:'DM Sans',sans-serif; font-size:0.72rem; font-weight:600;
      cursor:pointer; text-decoration:none; border:none;
      display:inline-flex; align-items:center; gap:4px;
      transition:opacity 0.13s, transform 0.11s;
    }
    .ba:hover { opacity:0.8; transform:translateY(-1px); }
    .ba svg { width:11px; height:11px; stroke:currentColor; fill:none; stroke-width:2.5; stroke-linecap:round; }
    .ba.vi { background:rgba(193,154,107,0.12); border:1px solid rgba(193,154,107,0.3); color:var(--brm); }
    .ba.ed { background:rgba(52,17,0,0.08); border:1px solid rgba(52,17,0,0.2); color:var(--br); }
    .ba.dl { background:rgba(192,57,43,0.08); border:1px solid rgba(192,57,43,0.2); color:#c0392b; }
    .es { text-align:center; padding:3rem 1rem; color:var(--mt); }
    .ei { width:44px; height:44px; margin:0 auto 0.9rem; background:var(--fn); border:1px solid var(--bd); border-radius:11px; display:flex; align-items:center; justify-content:center; }
    .ei svg { width:20px; height:20px; stroke:var(--gd); fill:none; stroke-width:1.8; stroke-linecap:round; }
 
    /* ── MODAL OVERLAY ── */
    .modal-overlay {
      display:none;
      position:fixed; inset:0; z-index:500;
      background:rgba(20,8,0,0.55);
      backdrop-filter:blur(6px);
      -webkit-backdrop-filter:blur(6px);
      align-items:center; justify-content:center;
      padding:1rem;
      overflow-y:auto;
    }
    .modal-overlay.open { display:flex; }
 
    /* ── MODAL CARD ── */
    .panel {
      background:var(--iv);
      border:1px solid var(--bd);
      border-radius:18px;
      overflow:hidden;
      width:100%;
      max-width:620px;
      margin:auto;
      box-shadow:0 32px 80px rgba(0,0,0,0.4);
      animation:modalIn 0.25s ease both;
    }
    @keyframes modalIn {
      from { opacity:0; transform:translateY(20px) scale(0.97); }
      to   { opacity:1; transform:translateY(0)   scale(1); }
    }
    .panel-hd {
      background:var(--br); padding:1.2rem 1.6rem;
      display:flex; align-items:center; justify-content:space-between;
    }
    .panel-hd h3 { font-family:'Cormorant Garamond',serif; font-size:1.1rem; font-weight:600; color:var(--iv); }
    .panel-close {
      display:inline-flex; align-items:center; justify-content:center;
      width:28px; height:28px; border-radius:50%;
      background:rgba(255,255,240,0.1); color:rgba(255,255,240,0.7);
      text-decoration:none; font-size:1.1rem; line-height:1; border:none; cursor:pointer;
      transition:background 0.14s, color 0.14s;
    }
    .panel-close:hover { background:rgba(255,255,240,0.22); color:var(--iv); }
    .panel-body { padding:1.5rem 1.6rem; max-height:80vh; overflow-y:auto; }
 
    /* form inside panel */
    .fg  { margin-bottom:0.95rem; }
    .fg2 { display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:0.95rem; }
    .fg label { display:block; font-size:0.67rem; font-weight:600; color:var(--br); letter-spacing:0.09em; text-transform:uppercase; margin-bottom:4px; }
    .req { color:var(--gd); margin-left:2px; }
    .fg input, .fg select, .fg textarea,
    .fg2 input, .fg2 select {
      width:100%; background:var(--fn); border:1.5px solid var(--bd);
      color:var(--tx); border-radius:8px; padding:8px 11px;
      font-family:'DM Sans',sans-serif; font-size:0.86rem;
      outline:none; transition:border-color 0.15s, box-shadow 0.15s;
    }
    .fg input:focus, .fg select:focus, .fg textarea:focus,
    .fg2 input:focus, .fg2 select:focus {
      border-color:var(--br); box-shadow:0 0 0 3px rgba(52,17,0,0.08);
    }
    .fg input::placeholder, .fg textarea::placeholder { color:#c0a880; }
    select option { background:var(--iv); color:var(--tx); }
    .fg textarea { resize:vertical; min-height:72px; }
    .form-actions { display:flex; gap:0.7rem; margin-top:1.1rem; }
    .btn-save {
      background:var(--br); color:var(--iv); border:none;
      padding:9px 20px; border-radius:8px;
      font-family:'DM Sans',sans-serif; font-size:0.86rem; font-weight:700;
      cursor:pointer; flex:1; transition:background 0.14s;
    }
    .btn-save:hover { background:var(--brd); }
    .btn-cancel {
      background:transparent; border:1.5px solid var(--bd); color:var(--mt);
      padding:9px 16px; border-radius:8px;
      font-family:'DM Sans',sans-serif; font-size:0.86rem; font-weight:600;
      cursor:pointer; text-decoration:none;
      transition:background 0.14s, color 0.14s;
    }
    .btn-cancel:hover { background:var(--fn); color:var(--br); }
 
    /* view panel details */
    .vm-hero {
      background:var(--br); padding:1.3rem 1.4rem;
      display:flex; align-items:center; gap:1rem;
    }
    .vm-av {
      width:52px; height:52px; border-radius:50%;
      background:var(--gd); border:2px solid rgba(255,255,240,0.22);
      display:flex; align-items:center; justify-content:center;
      font-family:'Cormorant Garamond',serif; font-size:1.3rem; font-weight:700; color:var(--br); flex-shrink:0;
    }
    .vm-name { font-family:'Cormorant Garamond',serif; font-size:1.2rem; font-weight:700; color:var(--iv); }
    .vm-acct { font-size:0.72rem; color:rgba(255,255,240,0.45); margin-top:2px; }
    .detail-grid { display:grid; grid-template-columns:1fr 1fr; }
    .di { padding:0.8rem 1.1rem; border-bottom:1px solid var(--bd); }
    .di:nth-child(odd) { border-right:1px solid var(--bd); }
    .dl { font-size:0.62rem; font-weight:700; color:var(--mt); letter-spacing:0.1em; text-transform:uppercase; margin-bottom:3px; }
    .dv { font-size:0.86rem; color:var(--tx); font-weight:500; }
    .dv.amt2 { font-family:'Cormorant Garamond',serif; font-size:1.05rem; font-weight:700; color:var(--br); }
    .dv.red2 { color:#c0392b; }
    .notes-sec { padding:0.9rem 1.1rem; }
    .notes-sec .dl { margin-bottom:5px; }
    .notes-sec p { font-size:0.84rem; color:var(--mt); line-height:1.6; }
    .vm-actions { padding:0.9rem 1.1rem; border-top:1px solid var(--bd); display:flex; gap:0.7rem; }
    .btn-vm-edit {
      background:var(--br); color:var(--iv); border:none;
      padding:8px 18px; border-radius:7px;
      font-family:'DM Sans',sans-serif; font-size:0.82rem; font-weight:700;
      cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px;
      transition:background 0.14s;
    }
    .btn-vm-edit:hover { background:var(--brd); }
    .btn-vm-edit svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2.5; stroke-linecap:round; }
    .btn-vm-del {
      background:rgba(192,57,43,0.08); color:#c0392b; border:1px solid rgba(192,57,43,0.22);
      padding:8px 18px; border-radius:7px;
      font-family:'DM Sans',sans-serif; font-size:0.82rem; font-weight:700;
      cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px;
      transition:background 0.14s;
    }
    .btn-vm-del:hover { background:rgba(192,57,43,0.14); }
 
 
    /* ── HAMBURGER BUTTON (mobile only) ── */
    .hamburger {
      display:none; flex-direction:column; justify-content:center; gap:5px;
      background:none; border:none; cursor:pointer; padding:6px;
      z-index:200;
    }
    .hamburger span {
      display:block; width:22px; height:2px;
      background:var(--br); border-radius:2px;
      transition:transform 0.25s, opacity 0.2s;
    }
 
    /* ── SIDEBAR OVERLAY (mobile) ── */
    .sb-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,0.45); z-index:149;
    }
    .sb-overlay.open { display:block; }
 
    /* ── LARGE DESKTOP (1400px+) ── */
    @media (min-width:1400px) {
      .sb { width:260px; }
      .main { margin-left:0; }
      .stats { grid-template-columns:repeat(4,1fr); }
      .pb { padding:2rem 2.5rem; }
    }
 
    /* ── STANDARD DESKTOP (1100px–1399px) ── */
    @media (max-width:1399px) {
      .stats { grid-template-columns:repeat(4,1fr); }
    }
 
    /* ── SMALL DESKTOP / LARGE TABLET LANDSCAPE (900px–1100px) ── */
    @media (max-width:1100px) {
      .stats { grid-template-columns:repeat(2,1fr); }
      .sb { width:200px; }
    }
 
    /* ── TABLET PORTRAIT (600px–900px) ── */
    @media (max-width:900px) {
      body { display:block; }
 
      /* sidebar becomes a slide-in drawer */
      .sb {
        position:fixed; left:0; top:0; bottom:0;
        width:240px; z-index:150;
        transform:translateX(-100%);
        transition:transform 0.28s ease;
        height:100vh;
      }
      .sb.open { transform:translateX(0); }
 
      .main { margin-left:0; width:100%; }
 
      .topbar { padding:0 1rem; }
      .hamburger { display:flex; }
      .tb-date { display:none; }
 
 
      .stats { grid-template-columns:repeat(2,1fr); gap:0.75rem; }
      .pb { padding:1.2rem 1rem; }
      .fg2 { grid-template-columns:1fr; }
 
      thead th:nth-child(4),
      tbody td:nth-child(4) { display:none; }
    }
 
    /* ── MOBILE (below 600px) ── */
    @media (max-width:600px) {
      .stats { grid-template-columns:1fr 1fr; gap:0.6rem; }
      .sc { padding:0.85rem 1rem; }
      .sv { font-size:1.3rem; }
 
      .topbar { height:52px; padding:0 0.8rem; }
      .tb-title { font-size:1rem; }
      .btn-add-top span { display:none; }
      .btn-add-top { padding:7px 10px; }
 
      .pb { padding:1rem 0.8rem; }
      .table-head { flex-direction:column; align-items:stretch; gap:0.6rem; }
      .sw input { width:100%; }
 
      table { font-size:0.76rem; }
      thead th, tbody td { padding:7px 8px; }
 
      /* hide less critical columns on mobile */
      thead th:nth-child(4),
      tbody td:nth-child(4),
      thead th:nth-child(5),
      tbody td:nth-child(5),
      thead th:nth-child(7),
      tbody td:nth-child(7) { display:none; }
 
      .ba { padding:4px 7px; font-size:0.68rem; }
      .ba svg { display:none; }
 
      .fg2 { grid-template-columns:1fr; }
 
      .detail-grid { grid-template-columns:1fr; }
      .di:nth-child(odd) { border-right:none; }
    }
 
    /* ── VERY SMALL MOBILE (below 400px) ── */
    @media (max-width:400px) {
      .stats { grid-template-columns:1fr; }
      .ac { flex-direction:column; gap:3px; }
      .ba { justify-content:center; }
    }
  </style>
</head>
<body>
 
<!-- SIDEBAR OVERLAY (mobile) -->
<div class="sb-overlay" id="sb-overlay" onclick="toggleSidebar()"></div>
 
<!-- SIDEBAR -->
<aside class="sb" id="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">
      <div class="sb-seal">
        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><polyline points="16 3 12 7 8 3"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="9.5" y1="14" x2="14.5" y2="14"/></svg>
      </div>
      <div>
        <div class="sb-name">Nova<span>Bank</span></div>
        <div class="sb-sub">Loan Recovery</div>
      </div>
    </div>
  </div>
 
  <nav class="sb-nav">
    <span class="nav-lbl">Main Menu</span>
    <a href="index.php" class="nav-item <?= $panel === 'none' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
    <a href="index.php?form=1" class="nav-item <?= $panel === 'form' && !$editing ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/></svg>
      Add Defaulter
    </a>
    <span class="nav-lbl">Records</span>
    <a href="index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      All Defaulters
    </a>
  </nav>
 
  <div class="sb-foot">
    <div class="sb-info">NovaBank &copy; <?= date('Y') ?><br/>Loan Recovery System</div>
  </div>
</aside>
 
<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <div class="tb-title">Loan Defaulters</div>
    <div class="tb-right">
      <span class="tb-date"><?= date('D, d M Y') ?></span>
      <a href="index.php?form=1" class="btn-add-top">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Defaulter
      </a>
    </div>
  </div>
 
  <div class="pb">
 
    <!-- STATS -->
    <div class="stats">
      <div class="sc">
        <div class="si b"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div class="sv"><?= $total ?></div><div class="sl">Total Defaulters</div>
      </div>
      <div class="sc gd">
        <div class="si g"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="sv" style="font-size:1.1rem"><?= 'RWF ' . number_format($total_due, 0) ?></div><div class="sl">Total Amount Due</div>
      </div>
      <div class="sc rd">
        <div class="si r"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
        <div class="sv"><?= $critical ?></div><div class="sl">Critical Cases</div>
      </div>
      <div class="sc md">
        <div class="si m"><svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
        <div class="sv"><?= $overdue ?></div><div class="sl">Overdue Loans</div>
      </div>
    </div>
 
    <?php if (isset($_GET['msg'])): ?>
      <?php $msgs = ['created'=>'Defaulter record added successfully.','updated'=>'Record updated successfully.','deleted'=>'Record deleted successfully.','error'=>'Something went wrong. Please try again.']; ?>
      <div class="alert s"><?= $msgs[$_GET['msg']] ?? '' ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="alert e"><?= htmlspecialchars(urldecode($_GET['error'])) ?></div>
    <?php endif; ?>
 
    <!-- CONTENT ROW -->
    <div class="content-row">
 
      <!-- TABLE -->
      <div class="table-card">
        <div class="table-head">
          <div class="th-title">Unpaid Loan Records</div>
          <div class="sw">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="si" placeholder="Search name, account…" oninput="filt()"/>
          </div>
        </div>
        <div class="tw">
          <table id="tbl">
            <thead>
              <tr>
                <th>#</th><th>Client</th><th>Account No.</th>
                <th>Loan Type</th><th>Loan Amount</th><th>Amount Due</th>
                <th>Due Date</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($all) > 0): ?>
                <?php foreach ($all as $i => $r):
                  $ini = strtoupper(substr($r['full_name'], 0, 1));
                  $sc  = strtolower($r['status']);
                ?>
                <tr>
                  <td class="mu"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></td>
                  <td>
                    <div class="nc">
                      <div class="av"><?= $ini ?></div>
                      <div>
                        <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                        <small><?= htmlspecialchars($r['email'] ?: ($r['phone'] ?: '—')) ?></small>
                      </div>
                    </div>
                  </td>
                  <td class="mu"><?= htmlspecialchars($r['account_no']) ?></td>
                  <td class="mu"><?= htmlspecialchars($r['loan_type'] ?: '—') ?></td>
                  <td><span class="amt"><?= fmt($r['loan_amount']) ?></span></td>
                  <td><span class="amt red"><?= fmt($r['amount_due']) ?></span></td>
                  <td class="mu"><?= $r['due_date'] ? date('d M Y', strtotime($r['due_date'])) : '—' ?></td>
                  <td><span class="badge <?= $sc ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                  <td>
                    <div class="ac">
                      <a href="index.php?view=<?= $r['id'] ?>" class="ba vi">
                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        View
                      </a>
                      <a href="index.php?edit=<?= $r['id'] ?>" class="ba ed">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit
                      </a>
                      <a href="loan_crud.php?action=delete&id=<?= $r['id'] ?>" class="ba dl"
                         onclick="return confirm('Delete this record permanently?')">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                        Delete
                      </a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="9">
                  <div class="es">
                    <div class="ei"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                    <p style="font-size:.86rem;line-height:1.6">No defaulter records found.<br/>Click <strong>Add Defaulter</strong> to get started.</p>
                  </div>
                </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
 
    </div><!-- end content-row -->
 
    <?php if ($panel === 'form'): ?>
    <!-- FORM MODAL -->
    <div class="modal-overlay open" id="form-modal" onclick="closeOnOverlay(event,'form-modal')">
      <div class="panel">
        <div class="panel-hd">
          <h3><?= $editing ? 'Edit Defaulter' : 'Add New Defaulter' ?></h3>
          <a href="index.php" class="panel-close" title="Close">&times;</a>
        </div>
        <div class="panel-body">
          <form action="loan_crud.php" method="POST">
            <input type="hidden" name="action"       value="<?= $editing ? 'update' : 'create' ?>"/>
            <input type="hidden" name="defaulter_id" value="<?= $editing ? $editing['id'] : '' ?>"/>
            <div class="fg2">
              <div>
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="full_name" value="<?= val('full_name', $editing) ?>" placeholder="e.g. Jean Paul" required autofocus/>
              </div>
              <div>
                <label>Account No. <span class="req">*</span></label>
                <input type="text" name="account_no" value="<?= val('account_no', $editing) ?>" placeholder="NB-2024-001" required/>
              </div>
            </div>
            <div class="fg2">
              <div>
                <label>Phone</label>
                <input type="tel" name="phone" value="<?= val('phone', $editing) ?>" placeholder="+250 7XX XXX"/>
              </div>
              <div>
                <label>Email</label>
                <input type="email" name="email" value="<?= val('email', $editing) ?>" placeholder="client@mail.com"/>
              </div>
            </div>
            <div class="fg2">
              <div>
                <label>Loan Type</label>
                <select name="loan_type">
                  <option value="">Select</option>
                  <?php foreach(['Personal Loan','Business Loan','Mortgage','Agricultural Loan','Education Loan','Emergency Loan'] as $lt): ?>
                    <option <?= sel('loan_type', $lt, $editing) ?>><?= $lt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label>Status</label>
                <input type="text" value="Auto-calculated from due date" disabled style="background:#f5ede2;color:#6b4030;font-style:italic;"/>
              </div>
            </div>
            <div class="fg2">
              <div>
                <label>Loan Amount (RWF) <span class="req">*</span></label>
                <input type="number" name="loan_amount" value="<?= val('loan_amount', $editing) ?>" placeholder="500000" min="0" step="0.01" required/>
              </div>
              <div>
                <label>Amount Due (RWF) <span class="req">*</span></label>
                <input type="number" name="amount_due" value="<?= val('amount_due', $editing) ?>" placeholder="250000" min="0" step="0.01" required/>
              </div>
            </div>
            <div class="fg">
              <label>Payment Due Date</label>
              <input type="date" name="due_date" value="<?= val('due_date', $editing) ?>"/>
            </div>
            <div class="fg">
              <label>Notes / Remarks</label>
              <textarea name="notes" placeholder="Any relevant notes…"><?= val('notes', $editing) ?></textarea>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-save"><?= $editing ? 'Update Record' : 'Save Defaulter' ?></button>
              <a href="index.php" class="btn-cancel">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php elseif ($panel === 'view' && $viewing): ?>
    <!-- VIEW MODAL -->
    <div class="modal-overlay open" id="view-modal" onclick="closeOnOverlay(event,'view-modal')">
      <div class="panel">
        <div class="panel-hd">
          <h3>Client Detail</h3>
          <a href="index.php" class="panel-close" title="Close">&times;</a>
        </div>
        <div class="vm-hero">
          <div class="vm-av"><?= strtoupper(substr($viewing['full_name'], 0, 1)) ?></div>
          <div>
            <div class="vm-name"><?= htmlspecialchars($viewing['full_name']) ?></div>
            <div class="vm-acct"><?= htmlspecialchars($viewing['account_no']) ?></div>
            <span class="badge <?= strtolower($viewing['status']) ?>" style="margin-top:6px;display:inline-flex">
              <?= htmlspecialchars($viewing['status']) ?>
            </span>
          </div>
        </div>
        <div class="detail-grid">
          <div class="di"><div class="dl">Phone</div><div class="dv"><?= htmlspecialchars($viewing['phone'] ?: '—') ?></div></div>
          <div class="di"><div class="dl">Email</div><div class="dv"><?= htmlspecialchars($viewing['email'] ?: '—') ?></div></div>
          <div class="di"><div class="dl">Loan Type</div><div class="dv"><?= htmlspecialchars($viewing['loan_type'] ?: '—') ?></div></div>
          <div class="di"><div class="dl">Due Date</div><div class="dv"><?= $viewing['due_date'] ? date('d M Y', strtotime($viewing['due_date'])) : '—' ?></div></div>
          <div class="di"><div class="dl">Loan Amount</div><div class="dv amt2"><?= fmt($viewing['loan_amount']) ?></div></div>
          <div class="di"><div class="dl">Amount Due</div><div class="dv amt2 red2"><?= fmt($viewing['amount_due']) ?></div></div>
          <div class="di"><div class="dl">Date Added</div><div class="dv"><?= date('d M Y', strtotime($viewing['created_at'])) ?></div></div>
          <div class="di"><div class="dl">Last Updated</div><div class="dv"><?= date('d M Y', strtotime($viewing['updated_at'])) ?></div></div>
        </div>
        <?php if ($viewing['notes']): ?>
          <div class="notes-sec">
            <div class="dl">Notes</div>
            <p><?= nl2br(htmlspecialchars($viewing['notes'])) ?></p>
          </div>
        <?php endif; ?>
        <div class="vm-actions">
          <a href="index.php?edit=<?= $viewing['id'] ?>" class="btn-vm-edit">
            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Record
          </a>
          <a href="loan_crud.php?action=delete&id=<?= $viewing['id'] ?>" class="btn-vm-del"
             onclick="return confirm('Delete this record permanently?')">
            Delete
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- end pb -->
</div><!-- end main -->
 
<script>
  function filt() {
    const q = document.getElementById('si').value.toLowerCase();
    document.querySelectorAll('#tbl tbody tr').forEach(r => {
      r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }
  function closeOnOverlay(e, id) {
    if (e.target === document.getElementById(id)) {
      window.location.href = 'index.php';
    }
  }
  // Close modal on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') window.location.href = 'index.php';
  });
</script>
<script>
function toggleSidebar() {
  const sb  = document.getElementById('sidebar');
  const ov  = document.getElementById('sb-overlay');
  sb.classList.toggle('open');
  ov.classList.toggle('open');
}
// Close sidebar on nav link click (mobile)
document.querySelectorAll('.nav-item').forEach(function(item) {
  item.addEventListener('click', function() {
    if (window.innerWidth <= 900) {
      document.getElementById('sidebar').classList.remove('open');
      document.getElementById('sb-overlay').classList.remove('open');
    }
  });
});
</script>
</body>
</html>