# Seat / Table Dual-Pool Management

## Feature Brief

Fundraiser events have two distinct **pools** of physical tables:

| Pool | `booking_mode` | Can be booked as | Visibility on frontend |
| :--- | :--- | :--- | :--- |
| **Table Pool** | `'table'` | A whole table only | Shows up in "Table Booking" mode |
| **Seat Pool** | `'seat'` | Individual seats only | Contributes seats to "Individual Seat Booking" mode |

### Core booking rules

- **Seat Pool — fully automatic allocation.** The user only picks a *quantity*. The system assigns the next available seat numbers in ascending order (lowest number first), skipping any blocked or already-booked seats. The booker has zero control over which specific seat numbers they receive.
- **Confirmation emails with seat numbers.** Auto-allocated seat numbers will appear in the booking confirmation email sent to the attendee once payment is completed.
- **Table Pool — booker chooses.** The user picks which specific table(s) they want from a list of available (non-blocked, non-booked) table-pool tables.
- **Admin blocking.** Any individual table or any individual seat number can be administratively blocked. Blocked items are invisible on the frontend and skipped by auto-allocation. The admin can unblock them at any time.

Admins can also move **unbooked, unblocked** tables freely between the two pools at any time.

---

## User Stories

### Admin

1. **Designate pools when generating inventory**
   - When batch-generating tables for a tier, choose how many go into the
     Table Pool and how many go into the Seat Pool.
   - A single mode radio (`Seat Pool` / `Table Pool`) is added to the
     existing Generate Tables form. Run the generator twice for a mixed batch.

2. **Monitor pools independently**
   - The event editor shows a **Pool Monitor** card with two gauges side by side:
     - **Table Pool**: `X of Y whole-tables remaining`
     - **Seat Pool**: `A total seats · B booked · C remaining`
   - Blocked items are shown separately so admins know why counts differ.

3. **Move a free table between pools**
   - Click **"→ Seat Pool"** / **"→ Table Pool"** on any table row with
     `is_booked = 0` and `is_blocked = 0`.
   - Booked or blocked tables cannot be moved.

4. **Block a table (Table Pool or Seat Pool)**
   - Any unbooked table can be set `is_blocked = 1` by the admin.
   - A blocked Table Pool table disappears from the frontend table picker.
   - A blocked Seat Pool table is excluded from seat capacity and
     auto-allocation — its seat numbers are treated as if they do not exist.
   - Admin can unblock at any time as long as the table is not booked.

5. **Block individual seat numbers (Seat Pool — Combined Grid)**
   - Admin can mark specific seat numbers (e.g. seat 3, seat 17) as blocked.
   - Blocked seat numbers are stored in `wp_lccl_de_fr_seat_blocks`.
   - Auto-allocation skips blocked seat numbers.
   - Admin sees a single **combined** seat-number grid across all seat-pool tables/tiers for the event showing: Available / Booked / Blocked.
   - Clicking an available seat blocks it; clicking a blocked seat unblocks it.

### Front-end Booker

6. **Individual Seat booking** draws only from the Seat Pool.
   - The user **only picks a quantity**. Seat numbers are assigned automatically
     by the server in ascending order, skipping blocked and already-booked seats.
   - No seat picker, no seat map interaction.
   - Available quantity shown on the form = total unblocked, unbooked seat-pool seats.

7. **Table Booking** draws only from the Table Pool.
   - The table selection list only shows tables where
     `booking_mode = 'table'`, `is_booked = 0`, and `is_blocked = 0`.

---

## Current State

```
wp_lccl_de_fr_tables
  id | event_id | type_id | table_label | sort_order | is_booked

(no seat_blocks table)
```

All tables are currently interchangeable; the booking type is decided on the
**booking** row, not the **table** row. There is no concept of blocking.

---

## 1. Database Changes

### 1a. Add `booking_mode` and `is_blocked` columns to `wp_lccl_de_fr_tables`

```sql
ALTER TABLE `{prefix}lccl_de_fr_tables`
  ADD COLUMN `booking_mode` varchar(10) NOT NULL DEFAULT 'seat' AFTER `is_booked`,
  ADD COLUMN `is_blocked`   tinyint(1)  NOT NULL DEFAULT 0       AFTER `booking_mode`;
```

- **`booking_mode`**: `'seat'` (default) or `'table'`.
- **`is_blocked`**: `1` = admin has blocked this table. Blocked tables are invisible
  on the frontend and cannot receive bookings. Seat-pool blocked tables are
  also excluded from seat capacity and auto-allocation.

### 1b. New `wp_lccl_de_fr_seat_blocks` table

Stores individually blocked seat numbers for a given event.

```sql
CREATE TABLE `{prefix}lccl_de_fr_seat_blocks` (
    id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    event_id    bigint(20) unsigned NOT NULL,
    seat_number int(11) NOT NULL,
    blocked_by  bigint(20) unsigned DEFAULT NULL,
    blocked_at  datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY event_seat (event_id, seat_number),
    KEY event_id (event_id)
) {$charset};
```

- `blocked_by`: WP user ID of the admin who created the block (audit trail).
- A seat number is only blockable if it has not been allocated to a paid booking.
- Unblocking deletes the row.

### 1c. Schema version bump

Increment `LCCL_DE_Schema::VERSION` (e.g. `'7'` → `'8'`).

Add an upgrade block in `maybe_install()`:

```php
$tables_tbl      = $wpdb->prefix . 'lccl_de_fr_tables';
$seat_blocks_tbl = $wpdb->prefix . 'lccl_de_fr_seat_blocks';

if ( version_compare( $stored, '8', '<' ) ) {
    // Add booking_mode column
    $col = $wpdb->get_var( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tables_tbl}'
        AND COLUMN_NAME = 'booking_mode'" );
    if ( ! $col ) {
        $wpdb->query( "ALTER TABLE `{$tables_tbl}`
            ADD COLUMN `booking_mode` varchar(10) NOT NULL DEFAULT 'seat' AFTER `is_booked`" );
    }
    // Add is_blocked column
    $col2 = $wpdb->get_var( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tables_tbl}'
        AND COLUMN_NAME = 'is_blocked'" );
    if ( ! $col2 ) {
        $wpdb->query( "ALTER TABLE `{$tables_tbl}`
            ADD COLUMN `is_blocked` tinyint(1) NOT NULL DEFAULT 0 AFTER `booking_mode`" );
    }
    // Create seat_blocks table
    $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$seat_blocks_tbl}` (
        id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id    bigint(20) unsigned NOT NULL,
        seat_number int(11) NOT NULL,
        blocked_by  bigint(20) unsigned DEFAULT NULL,
        blocked_at  datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY event_seat (event_id, seat_number),
        KEY event_id (event_id)
    ) {$charset}" );
}
```

> `dbDelta` does not reliably add columns to existing tables. An explicit
> `ALTER TABLE` inside the upgrade path is the correct WordPress pattern.

---

## 2. `class-lccl-de-fundraiser.php`

### 2a. New table name helper

```php
public static function seat_blocks_table() { global $wpdb; return $wpdb->prefix . 'lccl_de_fr_seat_blocks'; }
```

### 2b. `CREATE TABLE` DDL (for fresh installs)

Add `booking_mode` and `is_blocked` to the `$tables` DDL, and create the seat blocks table inside `install()`:

```php
$tables = self::tables_table();
dbDelta( "CREATE TABLE {$tables} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    event_id bigint(20) unsigned NOT NULL,
    type_id bigint(20) unsigned NOT NULL,
    table_label varchar(20) NOT NULL,
    sort_order int(11) NOT NULL DEFAULT 0,
    is_booked tinyint(1) NOT NULL DEFAULT 0,
    booking_mode varchar(10) NOT NULL DEFAULT 'seat',
    is_blocked tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY  (id),
    KEY event_id (event_id),
    KEY type_id (type_id),
    KEY is_booked (is_booked),
    KEY booking_mode (booking_mode),
    KEY is_blocked (is_blocked)
) {$charset};" );

$sb = self::seat_blocks_table();
dbDelta( "CREATE TABLE {$sb} (
    id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    event_id    bigint(20) unsigned NOT NULL,
    seat_number int(11) NOT NULL,
    blocked_by  bigint(20) unsigned DEFAULT NULL,
    blocked_at  datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY event_seat (event_id, seat_number),
    KEY event_id (event_id)
) {$charset};" );
```

### 2c. New AJAX actions in `init()`

```php
add_action( 'wp_ajax_lccl_de_fundraiser_set_table_mode',    array( __CLASS__, 'ajax_set_table_mode' ) );
add_action( 'wp_ajax_lccl_de_fundraiser_toggle_table_block', array( __CLASS__, 'ajax_toggle_table_block' ) );
add_action( 'wp_ajax_lccl_de_fundraiser_toggle_seat_block',  array( __CLASS__, 'ajax_toggle_seat_block' ) );
```

### 2d. `ajax_generate_inventory()` — accept `mode` field

```php
$mode = in_array( $raw['mode'] ?? 'seat', array( 'seat', 'table' ), true )
      ? $raw['mode'] : 'seat';

$wpdb->insert( self::tables_table(), array(
    'event_id'     => $event_id,
    'type_id'      => $type_id,
    'table_label'  => $label,
    'sort_order'   => $num,
    'is_booked'    => 0,
    'booking_mode' => $mode,
    'is_blocked'   => 0,
) );
```

### 2e. `ajax_set_table_mode()` — new method

```php
public static function ajax_set_table_mode() {
    self::nonce_check();
    global $wpdb;
    $table_id = (int) ( $_POST['table_id'] ?? 0 );
    $mode     = in_array( $_POST['mode'] ?? '', array( 'seat', 'table' ), true )
                ? sanitize_key( $_POST['mode'] ) : '';
    if ( ! $table_id || ! $mode ) {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }
    $tbl = self::tables_table();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT is_booked, is_blocked FROM `{$tbl}` WHERE id = %d", $table_id
    ), ARRAY_A );
    if ( ! $row || (int) $row['is_booked'] ) {
        wp_send_json_error( array( 'message' => 'Cannot move a booked table.' ) );
    }
    if ( (int) $row['is_blocked'] ) {
        wp_send_json_error( array( 'message' => 'Unblock the table before moving it to a different pool.' ) );
    }
    $wpdb->update( self::tables_table(), array( 'booking_mode' => $mode ), array( 'id' => $table_id ) );
    wp_send_json_success( array( 'booking_mode' => $mode ) );
}
```

### 2f. `ajax_toggle_table_block()` — new method

Blocks or unblocks a table. A booked table cannot be blocked or unblocked.

```php
public static function ajax_toggle_table_block() {
    self::nonce_check();
    global $wpdb;
    $table_id = (int) ( $_POST['table_id'] ?? 0 );
    if ( ! $table_id ) { wp_send_json_error( array( 'message' => 'Invalid request.' ) ); }
    $tbl = self::tables_table();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT is_booked, is_blocked FROM `{$tbl}` WHERE id = %d", $table_id
    ), ARRAY_A );
    if ( ! $row || (int) $row['is_booked'] ) {
        wp_send_json_error( array( 'message' => 'Cannot block/unblock a booked table.' ) );
    }
    $new = (int) $row['is_blocked'] ? 0 : 1;
    $wpdb->update( self::tables_table(), array( 'is_blocked' => $new ), array( 'id' => $table_id ) );
    wp_send_json_success( array( 'is_blocked' => $new ) );
}
```

### 2g. `ajax_toggle_seat_block()` — new method

Blocks or unblocks an individual seat number for an event.
Blocking is rejected if the seat number appears in any paid booking.

```php
public static function ajax_toggle_seat_block() {
    self::nonce_check();
    global $wpdb;
    $event_id    = (int) ( $_POST['event_id'] ?? 0 );
    $seat_number = (int) ( $_POST['seat_number'] ?? 0 );
    if ( ! $event_id || $seat_number < 1 ) {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }
    // Check if seat is already paid/allocated
    $bk   = self::bookings_table();
    $rows = (array) $wpdb->get_results( $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        "SELECT seat_numbers FROM `{$bk}` WHERE event_id = %d AND booking_type = 'individual' AND status = 'paid'",
        $event_id
    ), ARRAY_A );
    foreach ( $rows as $r ) {
        if ( in_array( $seat_number, array_map( 'intval', explode( ',', $r['seat_numbers'] ?? '' ) ), true ) ) {
            wp_send_json_error( array( 'message' => "Seat #{$seat_number} is allocated to a paid booking and cannot be blocked." ) );
        }
    }
    $sb = self::seat_blocks_table();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $exists = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$sb}` WHERE event_id = %d AND seat_number = %d", $event_id, $seat_number
    ) );
    if ( $exists ) {
        // Unblock
        $wpdb->delete( $sb, array( 'event_id' => $event_id, 'seat_number' => $seat_number ) );
        wp_send_json_success( array( 'is_blocked' => 0, 'seat_number' => $seat_number ) );
    } else {
        // Block
        $wpdb->insert( $sb, array(
            'event_id'    => $event_id,
            'seat_number' => $seat_number,
            'blocked_by'  => get_current_user_id(),
            'blocked_at'  => current_time( 'mysql' ),
        ) );
        wp_send_json_success( array( 'is_blocked' => 1, 'seat_number' => $seat_number ) );
    }
}
```

### 2h. New `get_blocked_seat_numbers( $event_id )` helper

```php
public static function get_blocked_seat_numbers( $event_id ) {
    global $wpdb;
    $sb = self::seat_blocks_table();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT seat_number FROM `{$sb}` WHERE event_id = %d ORDER BY seat_number ASC", $event_id
    ), ARRAY_A );
    return array_map( function( $r ) { return (int) $r['seat_number']; }, $rows );
}
```

### 2i. `get_available_seat_numbers()` — full rewrite

Must now exclude **booked seats**, **blocked seat numbers**, and seats whose
parent table is blocked (`is_blocked = 1`).

Auto-allocation always takes seats from the **lowest available number upward**
(`array_slice( $available, 0, $qty )` already does this).

```php
public static function get_available_seat_numbers( $event_id ) {
    global $wpdb;
    $bk = self::bookings_table();
    $tb = self::tables_table();
    $tt = self::table_types_table();
    $sb = self::seat_blocks_table();

    // 1. Total seat capacity: seat-pool tables that are NOT blocked
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $total_capacity = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(ty.capacity), 0)
         FROM `{$tb}` ta
         JOIN `{$tt}` ty ON ta.type_id = ty.id
         WHERE ta.event_id = %d AND ta.booking_mode = 'seat' AND ta.is_blocked = 0",
        $event_id
    ) );
    if ( $total_capacity < 1 ) { return array(); }

    // 2. Already-paid seat numbers
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows  = (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT seat_numbers FROM `{$bk}`
         WHERE event_id = %d AND booking_type = 'individual' AND status = 'paid'",
        $event_id
    ), ARRAY_A );
    $booked = array();
    foreach ( $rows as $row ) {
        if ( ! empty( $row['seat_numbers'] ) ) {
            foreach ( explode( ',', $row['seat_numbers'] ) as $n ) {
                $booked[] = (int) trim( $n );
            }
        }
    }

    // 3. Admin-blocked seat numbers
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $blocked_rows = (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT seat_number FROM `{$sb}` WHERE event_id = %d", $event_id
    ), ARRAY_A );
    $blocked_seats = array_map( function( $r ) { return (int) $r['seat_number']; }, $blocked_rows );

    // 4. Exclude both — sorted ascending so lowest seats are always allocated first
    $all       = range( 1, $total_capacity );
    $unavail   = array_unique( array_merge( $booked, $blocked_seats ) );
    return array_values( array_diff( $all, $unavail ) );
    // Caller: $allocated = array_slice( $available, 0, $qty );
}
```

### 2j. `get_available_tables_by_type()` — add blocking filter

```php
return (array) $wpdb->get_results( $wpdb->prepare(
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    "SELECT * FROM `{$t}`
     WHERE event_id = %d AND type_id = %d
       AND booking_mode = 'table'
       AND is_booked = 0
       AND is_blocked = 0
     ORDER BY sort_order ASC",
    $event_id, $type_id
), ARRAY_A );
```

### 2g. New `get_pool_stats( $event_id )` helper

```php
public static function get_pool_stats( $event_id ) {
    global $wpdb;
    $ta = self::tables_table();
    $tt = self::table_types_table();
    $bk = self::bookings_table();

    $tables_total  = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$ta}` WHERE event_id = %d AND booking_mode = 'table'", $event_id ) );
    $tables_free   = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$ta}` WHERE event_id = %d AND booking_mode = 'table' AND is_booked = 0", $event_id ) );

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $seats_total   = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(ty.capacity), 0)
         FROM `{$ta}` ta JOIN `{$tt}` ty ON ta.type_id = ty.id
         WHERE ta.event_id = %d AND ta.booking_mode = 'seat'", $event_id ) );
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $seats_booked  = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(qty), 0) FROM `{$bk}`
         WHERE event_id = %d AND booking_type = 'individual' AND status = 'paid'", $event_id ) );

    return array(
        'tables_total'  => $tables_total,
        'tables_free'   => $tables_free,
        'tables_booked' => $tables_total - $tables_free,
        'seats_total'   => $seats_total,
        'seats_free'    => max( 0, $seats_total - $seats_booked ),
        'seats_booked'  => $seats_booked,
    );
}
```

---

## 3. `class-lccl-de-fundraiser-form.php`

### Server-side booking guard in `handle_submit()`

Verify that each selected table has `booking_mode = 'table'`:

```php
if ( 'table' === $booking_type ) {
    foreach ( $selected_tables as $tid ) {
        $tid = (int) $tid;
        $tbl = LCCL_DE_Fundraiser::tables_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT is_booked, booking_mode FROM `{$tbl}` WHERE id = %d", $tid
        ), ARRAY_A );
        if ( ! $row || $row['booking_mode'] !== 'table' ) {
            wp_die( esc_html__( 'Selected table is not available for whole-table booking.', 'lccl-de' ) );
        }
        if ( (int) $row['is_booked'] === 1 ) {
            wp_die( esc_html__( 'One or more selected tables have just been booked. Please try again.', 'lccl-de' ) );
        }
    }
}
```

### 3b. Confirmation Email Dispatch on Payment Completion

When a booking transitions to `status = 'paid'`, the system sends an HTML confirmation email to `$attendee_email`:

- **Individual Seat Bookings (`booking_type = 'individual'`)**:
  - The email explicitly lists the **auto-allocated seat numbers** (e.g. `Seat #4, Seat #5, Seat #6`), the total quantity of seats, amount paid, transaction receipt, and instructions to present the confirmation email or reference at the ticket counter.
- **Whole Table Bookings (`booking_type = 'table'`)**:
  - The email lists the selected table label(s) (e.g. `Table VIP-01, Table VIP-02`), event date, venue, and receipt.

---

## 4. `class-lccl-de-admin-programs.php`

### `fundraiser_vars()` — add `pool_stats` per event

```php
foreach ( $fr_events as &$ev ) {
    $ev['types']      = LCCL_DE_Fundraiser::get_table_types( $ev['id'] );
    $ev['tables']     = LCCL_DE_Fundraiser::get_tables( $ev['id'] );
    $ev['pool_stats'] = LCCL_DE_Fundraiser::get_pool_stats( $ev['id'] );  // NEW
}
```

---

## 5. `templates/admin-fundraiser.php`

### 5a. Pool Monitor card (new, in the Event Details column)

Insert after the event edit form card and before the Table Inventory card:

```html
<!-- Pool Monitor — populated by JS from event.pool_stats -->
<div class="lccl-fr-card" id="lccl-fr-pool-monitor" style="display:none;">
    <div class="lccl-fr-card__header">
        <h3 class="lccl-fr-card__title">Pool Monitor</h3>
    </div>
    <div class="lccl-fr-card__body">
        <div class="lccl-fr-pool-grid">
            <div class="lccl-fr-pool-gauge" id="fr-pool-gauge-table"></div>
            <div class="lccl-fr-pool-gauge" id="fr-pool-gauge-seat"></div>
        </div>
    </div>
</div>
```

### 5b. Mode radio in Generate Tables form

```html
<div>
    <label style="font-size: 11px; color: #777;">Pool</label>
    <label style="font-size:13px; display:inline-flex; align-items:center; gap:5px; margin-right:12px;">
        <input type="radio" name="mode" value="seat" checked> Seat Pool
    </label>
    <label style="font-size:13px; display:inline-flex; align-items:center; gap:5px;">
        <input type="radio" name="mode" value="table"> Table Pool
    </label>
</div>
```

### 5c. Per-table list

The per-table list is currently rendered entirely by JS. The PHP template needs
no change for the table rows themselves — the JS update below handles it.

---

## 6. `assets/js/lccl-de-admin-fundraiser.js`

### 6a. Pool stats renderer

```javascript
function renderPoolStats( poolStats, eventId ) {
    var $monitor = $( '#lccl-fr-pool-monitor' );
    if ( ! poolStats ) { $monitor.hide(); return; }
    $monitor.show();

    var tablesPct = poolStats.tables_total > 0
        ? Math.round( ( poolStats.tables_free / poolStats.tables_total ) * 100 ) : 0;
    var seatsPct  = poolStats.seats_total > 0
        ? Math.round( ( poolStats.seats_free / poolStats.seats_total ) * 100 ) : 0;

    $( '#fr-pool-gauge-table' ).html(
        '<strong style="font-size:13px; text-transform:uppercase; color:#777;">TABLE POOL</strong>' +
        '<div style="font-size:22px; font-weight:600; margin:6px 0;">' + poolStats.tables_free + '<span style="font-size:13px; color:#888;"> / ' + poolStats.tables_total + ' available</span></div>' +
        '<div class="lccl-fr-inventory-bar"><span style="width:' + tablesPct + '%;"></span></div>' +
        '<div style="font-size:11px; color:#888; margin-top:4px;">' + poolStats.tables_booked + ' whole tables booked</div>'
    );
    $( '#fr-pool-gauge-seat' ).html(
        '<strong style="font-size:13px; text-transform:uppercase; color:#777;">SEAT POOL</strong>' +
        '<div style="font-size:22px; font-weight:600; margin:6px 0;">' + poolStats.seats_free + '<span style="font-size:13px; color:#888;"> / ' + poolStats.seats_total + ' seats available</span></div>' +
        '<div class="lccl-fr-inventory-bar"><span style="width:' + seatsPct + '%;"></span></div>' +
        '<div style="font-size:11px; color:#888; margin-top:4px;">' + poolStats.seats_booked + ' individual seats booked</div>'
    );
}
```

Call from the event-select handler:

```javascript
renderPoolStats( data.pool_stats, data.id );
```

### 6b. Type-row rendering — add booking_mode badge + Move button

In the `data.types.forEach` loop, extend each table row HTML:

```javascript
// For each individual table under this type, render a row
var tableRows = '';
if ( data.tables ) {
    data.tables.forEach( function ( t ) {
        if ( parseInt( t.type_id ) !== parseInt( type.id ) ) return;
        var modeLabel = t.booking_mode === 'table' ? 'TABLE' : 'SEAT';
        var moveTo    = t.booking_mode === 'table' ? 'seat' : 'table';
        var moveTxt   = t.booking_mode === 'table' ? '→ Seat Pool' : '→ Table Pool';
        var moveBtn   = parseInt( t.is_booked ) === 0
            ? '<button type="button" class="lccl-fr-btn lccl-fr-btn--secondary lccl-fr-btn--sm js-move-table" '
              + 'data-id="' + t.id + '" data-current-mode="' + t.booking_mode + '">' + moveTxt + '</button>'
            : '';
        tableRows +=
            '<div style="display:flex; align-items:center; gap:10px; padding:5px 0; border-bottom:1px solid #f3f3f3;">' +
            '<span style="min-width:36px; font-weight:600;">' + t.table_label + '</span>' +
            '<span class="lccl-fr-badge lccl-fr-badge--' + t.booking_mode + '-mode">' + modeLabel + '</span>' +
            ( parseInt( t.is_booked ) ? '<span style="color:#c00; font-size:11px;">BOOKED</span>' : '<span style="color:#2a7a2a; font-size:11px;">Free</span>' ) +
            moveBtn +
            '</div>';
    } );
}
```

Append `tableRows` inside the type-row card.

### 6c. Move button handler

```javascript
$( document ).on( 'click', '.js-move-table', function () {
    var $btn        = $( this );
    var tableId     = $btn.data( 'id' );
    var currentMode = $btn.data( 'current-mode' );
    var newMode     = currentMode === 'table' ? 'seat' : 'table';
    showLoader();
    $.post( lcclFundraiser.ajaxUrl, {
        action:   'lccl_de_fundraiser_set_table_mode',
        nonce:    lcclFundraiser.nonce,
        table_id: tableId,
        mode:     newMode,
    }, function ( res ) {
        if ( res.success ) {
            showMsg( 'Table moved to ' + newMode + ' pool.', 'success' );
            setTimeout( function () { location.reload(); }, 600 );
        } else {
            showMsg( res.data.message || 'Error moving table.', 'error' );
        }
    } );
} );
```

---

## 7. `templates/fundraiser-form.php` (JS section)

### 7a. Seat panel — use only seat-pool tables for capacity

Replace the current `total_capacity` derivation with:

```javascript
// Build flat table list from fundData.tables (all type groups)
var allTables = [];
Object.keys( fundData.tables ).forEach( function ( tid ) {
    fundData.tables[ tid ].forEach( function ( t ) { allTables.push( t ); } );
} );

// Seat pool = seat-mode tables that are not yet booked
var seatPoolTables = allTables.filter( function ( t ) {
    return t.booking_mode === 'seat' && parseInt( t.is_booked ) === 0;
} );

// Compute total available seats (each table contributes capacity seats)
// Need capacity per table — it comes from type data
var typeCapacity = {};
fundData.types.forEach( function ( ty ) { typeCapacity[ ty.id ] = parseInt( ty.capacity ); } );

var availableSeats = seatPoolTables.reduce( function ( acc, t ) {
    return acc + ( typeCapacity[ t.type_id ] || 0 );
}, 0 );

// Cap the qty input max to availableSeats
inputQty.max = availableSeats;
if ( availableSeats === 0 ) {
    inputQty.setAttribute( 'disabled', 'disabled' );
}
```

### 7b. Table panel — show only table-pool tables

In the `selectType` change handler:

```javascript
var avail = ( fundData.tables[ tid ] || [] ).filter( function ( t ) {
    return t.booking_mode === 'table' && parseInt( t.is_booked ) === 0;   // NEW filter
} );
```

This is a single line change to the existing filter condition.

---

## 8. `assets/css/lccl-de-admin-fundraiser.css`

```css
/* Pool Monitor */
.lccl-fr-pool-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.lccl-fr-pool-gauge {
    padding: 16px;
    background: #fafafa;
    border: 1px solid #eee;
    border-radius: 4px;
}

/* Mode Badges */
.lccl-fr-badge--table-mode {
    background: #1a4a8a;
    color: #fff;
    font-size: 10px;
    padding: 2px 7px;
    border-radius: 3px;
    letter-spacing: 0.5px;
}

.lccl-fr-badge--seat-mode {
    background: #5a2d82;
    color: #fff;
    font-size: 10px;
    padding: 2px 7px;
    border-radius: 3px;
    letter-spacing: 0.5px;
}
```

---

## File Checklist

| File | Change |
| :--- | :--- |
| `includes/class-lccl-de-schema.php` | Bump `VERSION`, `ALTER TABLE` for `booking_mode` + `is_blocked`, create `seat_blocks` table |
| `includes/class-lccl-de-fundraiser.php` | `seat_blocks_table()`, updated DDL, 3 new AJAX actions, `get_blocked_seat_numbers()`, rewritten `get_available_seat_numbers()`, blocking filter in `get_available_tables_by_type()`, `get_pool_stats()` |
| `includes/class-lccl-de-fundraiser-form.php` | `booking_mode` guard + `is_blocked` guard in `handle_submit()`; automated confirmation email with allocated seat numbers upon payment completion |
| `includes/class-lccl-de-admin-programs.php` | `fundraiser_vars()` — add `pool_stats` + `blocked_seats` per event |
| `templates/admin-fundraiser.php` | Pool Monitor card, Mode radio in Generator, Block/Unblock buttons in table rows, Seat Block Grid panel |
| `assets/js/lccl-de-admin-fundraiser.js` | Pool stats renderer, mode badges, Move handler, Toggle Table Block handler, Seat Block Grid renderer + Toggle handler |
| `templates/fundraiser-form.php` | Filter seat/table panels by `booking_mode` + `is_blocked`; seat qty derived from unblocked seat-pool tables only |
| `assets/css/lccl-de-admin-fundraiser.css` | Pool gauge, mode badges, blocked-state styles, seat grid styles |
| `lccl-donations-and-events.php` | Bump `LCCL_DE_VERSION` for cache-busting |

---

## Execution Order

1. **Schema** — bump `VERSION`, add `ALTER TABLE` upgrade blocks, create `seat_blocks` table.
2. **`LCCL_DE_Fundraiser`** — add `is_blocked` to DDL, `seat_blocks_table()` helper, 3 new AJAX handlers, `get_blocked_seat_numbers()`, rewrite `get_available_seat_numbers()`, update `get_available_tables_by_type()`, add blocking to `get_pool_stats()`.
3. **`LCCL_DE_Fundraiser_Form`** — add `is_blocked` guard alongside `booking_mode` guard; wire confirmation email dispatch with allocated seat numbers.
4. **`LCCL_DE_Admin_Programs`** — extend `fundraiser_vars()` with `pool_stats` and `blocked_seats`.
5. **`templates/admin-fundraiser.php`** — Pool Monitor card, Mode radio, Block/Unblock buttons, Seat Block Grid panel.
6. **`lccl-de-admin-fundraiser.js`** — pool stats renderer, mode/block badges, Move handler, Toggle Block handlers, Seat Grid renderer.
7. **`templates/fundraiser-form.php`** — JS seat qty capped to unblocked seat-pool capacity; table picker filters out blocked tables.
8. **CSS** — pool gauges, mode/block badges, seat grid dot styles.
9. **Version bump** — `LCCL_DE_VERSION`.

---

## Edge Cases

| Scenario | Behaviour |
| :--- | :--- |
| Admin moves a booked table | Server rejects |
| Admin moves a blocked table | Server rejects — unblock first |
| Admin blocks an already-booked table | Server rejects |
| Admin blocks a seat number that is in a paid booking | Server rejects |
| Table Pool fully booked/blocked | Frontend shows "No tables available" |
| Seat Pool fully booked/blocked | `qty` max = 0; submit button stays disabled |
| Admin blocks seat 5; user requests qty = 3 starting from seat 4 | System allocates 4, 6, 7 — skips 5 |
| All seat-pool tables are blocked | Available seat capacity = 0 |
| All tables still in Seat Pool (fresh event) | "Table Booking" shows "No tables available" |
| Existing rows before migration | `booking_mode = 'seat'`, `is_blocked = 0` from column DEFAULTs |
| Admin generates tables with no mode radio selected | Falls back to `'seat'` server-side |
| Seat block grid shows a seat beyond current seat-pool capacity | Only seats within the current unblocked capacity are shown |

---

## Admin UI — Seat Block Grid Detail (Combined Scope)

The Seat Block Grid is rendered as a **single combined flat numbered dot grid per event**
across all seat-pool tables/tiers (rather than segmented per-tier):

- A single continuous sequence `1 … total_unblocked_seat_pool_capacity`.
- Represents the total individual seat inventory across all tiers designated in the Seat Pool.
- Keeps seat auto-allocation and admin seat-blocking unified with no cross-tier fragmentation.

```
 SEAT POOL — SEAT BLOCK MANAGER

  1  ●  2  ●  3  ✖  4  ●  5  ●  6  ■  7  ■  8  ●
  9  ●  10 ●  11 ●  12 ✖  13 ●  14 ■  ...

 Legend:  ● Available  ■ Booked  ✖ Admin Blocked
```

- Seat numbers run `1 … total_unblocked_seat_pool_capacity`.
- **●** — Click to block → AJAX `toggle_seat_block` → seat shows ✖.
- **✖** — Click to unblock → AJAX `toggle_seat_block` → seat shows ●.
- **■** — Booked; not clickable.
- Grid is rendered by JS using `event.pool_stats.seat_capacity_map` (an array
  built server-side from all seat-pool table capacities and the seat_blocks
  and bookings tables).

Admin sees totals below the grid:
```
 87 available  ·  12 booked  ·  3 blocked  ·  102 total
```

---

## Confirmed Decisions

- **Seat block grid scope**: **Combined** — The seat block grid is combined across all seat-pool tables/tiers into a single unified sequence `1 … N`.
- **Confirmation emails with seat numbers**: **Yes** — Auto-allocated seat numbers will appear in the attendee's booking confirmation email upon payment completion.

---

## Open Questions (confirm before implementation)

1. **Whole-table pricing**: Should table-pool bookings price by `table_type.price` (current) or `ticket_price × capacity`?
2. **Seat-per-person cap**: Keep the hard-coded `max="10"` or make it configurable per event?
3. **Bulk move**: Is a per-table Move button enough, or do we need a "Move all free tables of this tier" shortcut?
4. **Seat map visual**: After this feature, should the public-facing sidebar dot-grid reflect actual pool counts?
