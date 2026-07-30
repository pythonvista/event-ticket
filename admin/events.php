<?php
require_once __DIR__ . '/../includes/admin_auth.php';

function handle_image_upload(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // no file chosen — keep existing image
    }
    if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Image upload failed.'; return null; }
    if ($file['size'] > 2 * 1024 * 1024) { $errors[] = 'Image must be 2 MB or smaller.'; return null; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) { $errors[] = 'Only JPG and PNG images are allowed.'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) { $errors[] = 'Invalid image file.'; return null; }
    $name = uniqid('evt_') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../assets/img/' . $name)) {
        $errors[] = 'Could not save the image.';
        return null;
    }
    return $name;
}

$action = $_GET['action'] ?? 'list';
$errors = [];

// Load event being edited.
$editing = null;
if ($action === 'edit') {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('Event not found.');
        redirect('admin/events.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eventDate = $_POST['event_date'] ?? '';
    $eventTime = $_POST['event_time'] ?? '';
    $venue = trim($_POST['venue'] ?? '');
    $regular = (float) ($_POST['regular_price'] ?? 0);
    $vip = (float) ($_POST['vip_price'] ?? 0);
    $vvip = (float) ($_POST['vvip_price'] ?? 0);
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($name === '') { $errors[] = 'Event name is required.'; }
    if ($description === '') { $errors[] = 'Description is required.'; }
    if (!strtotime($eventDate)) { $errors[] = 'A valid date is required.'; }
    if (!strtotime($eventTime)) { $errors[] = 'A valid time is required.'; }
    if ($venue === '') { $errors[] = 'Venue is required.'; }
    if ($regular <= 0 || $vip <= 0 || $vvip <= 0) { $errors[] = 'All three ticket prices must be greater than zero.'; }

    $image = $errors ? null : handle_image_upload($_FILES['image'] ?? [], $errors);

    if (!$errors) {
        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO events (name, description, image, event_date, event_time, venue, regular_price, vip_price, vvip_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $description, $image ?? '', $eventDate, $eventTime, $venue, $regular, $vip, $vvip, $status]);
            flash('Event added.');
        } else {
            $stmt = $pdo->prepare('UPDATE events SET name = ?, description = ?, image = ?, event_date = ?, event_time = ?, venue = ?, regular_price = ?, vip_price = ?, vvip_price = ?, status = ? WHERE id = ?');
            $stmt->execute([$name, $description, $image ?? $editing['image'], $eventDate, $eventTime, $venue, $regular, $vip, $vvip, $status, $editing['id']]);
            flash('Event updated.');
        }
        redirect('admin/events.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE event_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        // Keep booking history intact: deactivate instead of deleting.
        $pdo->prepare("UPDATE events SET status = 'inactive' WHERE id = ?")->execute([$id]);
        flash('Event has bookings, so it was set to inactive instead of deleted.');
    } else {
        $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
        flash('Event deleted.');
    }
    redirect('admin/events.php');
}

$events = $pdo->query('SELECT * FROM events ORDER BY event_date ASC')->fetchAll();

$pageTitle = 'Manage Events';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <?php if (in_array($action, ['add', 'edit'], true)): ?>
  <h2 class="mb-4"><?= $action === 'add' ? 'Add Event' : 'Edit Event' ?></h2>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <?php $formValue = fn(string $field) => e($_POST[$field] ?? $editing[$field] ?? ''); ?>
  <form method="post" enctype="multipart/form-data" class="card card-body shadow-sm" style="max-width: 720px">
    <div class="mb-3"><label class="form-label">Event Name</label><input class="form-control" name="name" value="<?= $formValue('name') ?>" required></div>
    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" required><?= $formValue('description') ?></textarea></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Date</label><input type="date" class="form-control" name="event_date" value="<?= $formValue('event_date') ?>" required></div>
      <div class="col-md-6 mb-3"><label class="form-label">Time</label><input type="time" class="form-control" name="event_time" value="<?= $formValue('event_time') ?>" required></div>
    </div>
    <div class="mb-3"><label class="form-label">Venue</label><input class="form-control" name="venue" value="<?= $formValue('venue') ?>" required></div>
    <div class="row">
      <div class="col-md-4 mb-3"><label class="form-label">Regular Price (₦)</label><input type="number" step="0.01" min="1" class="form-control" name="regular_price" value="<?= $formValue('regular_price') ?>" required></div>
      <div class="col-md-4 mb-3"><label class="form-label">VIP Price (₦)</label><input type="number" step="0.01" min="1" class="form-control" name="vip_price" value="<?= $formValue('vip_price') ?>" required></div>
      <div class="col-md-4 mb-3"><label class="form-label">VVIP Price (₦)</label><input type="number" step="0.01" min="1" class="form-control" name="vvip_price" value="<?= $formValue('vvip_price') ?>" required></div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Image (JPG/PNG, max 2 MB<?= $action === 'edit' ? ' — leave empty to keep current' : '' ?>)</label>
        <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="active" <?= ($_POST['status'] ?? $editing['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= ($_POST['status'] ?? $editing['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary"><?= $action === 'add' ? 'Add Event' : 'Save Changes' ?></button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>admin/events.php">Cancel</a>
    </div>
  </form>
  <?php else: ?>
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Manage Events</h2>
    <a class="btn btn-primary" href="<?= BASE_URL ?>admin/events.php?action=add">+ Add Event</a>
  </div>
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Name</th><th>Date</th><th>Venue</th><th>Regular</th><th>VIP</th><th>VVIP</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($events as $event): ?>
          <tr>
            <td><?= e($event['name']) ?></td>
            <td class="small"><?= e(date('j M Y', strtotime($event['event_date']))) ?> <?= e(date('g:i A', strtotime($event['event_time']))) ?></td>
            <td class="small"><?= e($event['venue']) ?></td>
            <td><?= naira($event['regular_price']) ?></td>
            <td><?= naira($event['vip_price']) ?></td>
            <td><?= naira($event['vvip_price']) ?></td>
            <td><span class="badge bg-<?= $event['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst($event['status'])) ?></span></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>admin/events.php?action=edit&id=<?= (int) $event['id'] ?>">Edit</a>
              <form method="post" action="<?= BASE_URL ?>admin/events.php?action=delete" class="d-inline" onsubmit="return confirm('Delete this event?');">
                <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
