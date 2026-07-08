<?php

$pageTitle = "Support System - Sogasu";
$activePage = "support";
include '../includes/db.php';
include 'includes/header.php';

/* =========================
   FETCH SUPPORT TICKETS
========================= */

$ticketsQuery = $pdo->query("

    SELECT *

    FROM support_tickets

    ORDER BY id DESC

");

?>

<main class="main-content">

<?php include 'includes/topbar.php'; ?>

<div style="padding:1rem;">

    <!-- HEADER -->

    <div class="page-header" style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:2rem;
    ">

        <div>

            <h2 style="
                font-size:1.5rem;
                font-weight:700;
                color:#1e293b;
            ">
                Support System
            </h2>

            <p class="text-muted">
                Manage customer tickets and internal issues
            </p>

        </div>

        <button
            class="btn btn-primary"
            onclick="openTicketModal()"
        >

            <i class="ri-add-line"></i>

            Create Ticket

        </button>

    </div>

    <!-- TABLE -->

    <div class="table-box" style="
        background:white;
        border:1px solid #e2e8f0;
        border-radius:8px;
        padding:1rem;
    ">

        <table
            id="supportTable"
            style="
                width:100%;
                border-collapse:collapse;
            "
        >

            <thead>

                <tr style="
                    border-bottom:1px solid #e2e8f0;
                ">

                    <th style="padding:1rem; text-align:left;">
                        Ticket #
                    </th>

                   <th style="padding:1rem; text-align:left;">
    Ticket Name
</th>

                    <th style="padding:1rem; text-align:left;">
                        Issue Type
                    </th>

                    <th style="padding:1rem; text-align:left;">
                        Priority
                    </th>

                    <th style="padding:1rem; text-align:left;">
                        Status
                    </th>

                    <th style="padding:1rem; text-align:left;">
                        Cost
                    </th>

                    <th style="padding:1rem; text-align:left;">
                        Created Date
                    </th>

                    <th style="padding:1rem; text-align:left;">
                        Action
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php while ($row = $ticketsQuery->fetch(PDO::FETCH_ASSOC)) : ?>

                <tr style="
                    border-bottom:1px solid #f1f5f9;
                ">

                    <td style="
                        padding:1rem;
                        font-weight:600;
                    ">

                        <?= htmlspecialchars($row['ticket_no']) ?>

                    </td>

                    <td style="
                        padding:1rem;
                        color:#1e293b;
                    ">

<?= htmlspecialchars($row['ticket_name']) ?>
                    </td>

                    <td style="
                        padding:1rem;
                        color:#1e293b;
                    ">

                        <?= htmlspecialchars($row['issue_type']) ?>

                    </td>

                    <td style="padding:1rem;">

                        <?php

                        $priorityClass = 'badge-success';

                        if($row['priority'] == 'High'){
                            $priorityClass = 'badge-danger';
                        }

                        if($row['priority'] == 'Medium'){
                            $priorityClass = 'badge-warning';
                        }

                        if($row['priority'] == 'Critical'){
                            $priorityClass = 'badge-danger';
                        }

                        ?>

                        <span class="badge <?= $priorityClass ?>">

                            <?= htmlspecialchars($row['priority']) ?>

                        </span>

                    </td>

                    <td style="padding:1rem;">

                        <span class="badge badge-warning">

                            <?= htmlspecialchars($row['status']) ?>

                        </span>

                    </td>

                    <td style="padding:1rem;">

                        ₹ <?= number_format($row['remediation_cost'], 2) ?>

                    </td>

                    <td style="
                        padding:1rem;
                        color:#64748b;
                    ">

                        <?= date(
                            'd M Y',
                            strtotime($row['created_at'])
                        ) ?>

                    </td>

                    <td style="padding:1rem;">

                        <div style="
                            display:flex;
                            gap:0.5rem;
                        ">

                            <button
                                class="btn-icon"
                              onclick="viewTicket(
    '<?= $row['ticket_no'] ?>',
    '<?= htmlspecialchars($row['description']) ?>',
    '<?= htmlspecialchars($row['status']) ?>'
)"
                            >

                                <i class="ri-eye-line"></i>

                            </button>

                            <button
                                class="btn-icon"
                              onclick="raiseBill(
    '<?= $row['ticket_no'] ?>',
    '<?= $row['remediation_cost'] ?>'
)"
                            >

                                <i class="ri-bill-line"></i>

                            </button>

                        </div>

                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

</main>
<!-- RAISE BILL MODAL -->

<div id="billModal" class="modal">

    <div class="modal-card" style="
        width:430px;
        border-radius:14px;
        overflow:hidden;
        background:#fff;
    ">

        <div style="
            background:#16a34a;
            color:white;
            padding:1.2rem 1.5rem;
            display:flex;
            justify-content:space-between;
            align-items:center;
        ">

            <h3 style="
                margin:0;
                font-size:1.5rem;
                font-weight:700;
            ">
                Raise Ticket Bill
            </h3>

            <i
                class="ri-close-line"
                style="
                    font-size:1.8rem;
                    cursor:pointer;
                "
                onclick="closeModal('billModal')"
            ></i>

        </div>

        <div style="padding:1.5rem;">

            <div class="form-group" style="
                margin-bottom:1.2rem;
            ">

                <label class="form-label">
                    Ticket Number
                </label>

                <input
                    type="text"
                    id="bill_ticket_no"
                    class="form-control"
                    readonly
                >

            </div>

            <div class="form-group" style="
                margin-bottom:1.2rem;
            ">

                <label class="form-label">
                    Issue Amount (Correction Cost)
                </label>

               <input
    type="number"
    id="bill_amount"
    class="form-control"
    placeholder="0"
>

            </div>

            <div class="form-group" style="
                margin-bottom:1.5rem;
            ">

                <label class="form-label">
                    Billing Date
                </label>

                <input
                    type="date"
                    class="form-control"
                    value="<?php echo date('Y-m-d'); ?>"
                >

            </div>

            <button
                class="btn"
                style="
                    width:100%;
                    background:#16a34a;
                    color:white;
                "
            >
                Confirm & Raise Bill
            </button>

        </div>

    </div>

</div>
<!-- VIEW TICKET MODAL -->

<div id="viewModal" class="modal">

    <div class="modal-card" style="
        width:580px;
        border-radius:16px;
        overflow:hidden;
        background:#fff;
    ">

        <!-- HEADER -->

        <div style="
            background:#17233c;
            color:white;
            padding:1.2rem 1.5rem;
            display:flex;
            justify-content:space-between;
            align-items:center;
        ">

            <h3 style="
                margin:0;
                font-size:1.7rem;
                font-weight:700;
            ">

                Ticket Details:
                <span id="view_ticket_no">
                    TIC-101
                </span>

            </h3>

            <i
                class="ri-close-line"
                style="
                    font-size:2rem;
                    cursor:pointer;
                "
                onclick="closeModal('viewModal')"
            ></i>

        </div>

        <!-- BODY -->

        <div style="padding:1.5rem;">

            <!-- TOP -->

            <div style="
                display:flex;
                justify-content:space-between;
                margin-bottom:1.5rem;
            ">

                <!-- ISSUE -->

                <div style="width:65%;">

                    <h5 style="
                        margin:0 0 0.5rem 0;
                        color:#64748b;
                        font-size:0.85rem;
                        text-transform:uppercase;
                    ">
                        Issue
                    </h5>

                    <p style="
                        margin:0;
                        font-size:1rem;
                        color:#1e293b;
                        line-height:1.6;
                    ">

                       <span id="view_ticket_description"></span>

                    </p>

                </div>

                <!-- STATUS -->

                <div style="width:25%;">

                    <h5 style="
                        margin:0 0 0.5rem 0;
                        color:#64748b;
                        font-size:0.85rem;
                        text-transform:uppercase;
                    ">
                        Status
                    </h5>

                    <span style="
                        background:#fef3c7;
                        color:#d97706;
                        padding:6px 12px;
                        border-radius:6px;
                        font-size:0.85rem;
                        font-weight:600;
                    ">

<span id="view_ticket_status"></span>
                    </span>

                </div>

            </div>

            <!-- BOX -->

            <div style="
                background:#f8fafc;
                border-radius:12px;
                padding:1rem;
                margin-bottom:1.5rem;
            ">

                <h4 style="
                    margin-top:0;
                    margin-bottom:1rem;
                    color:#1e293b;
                    font-size:1rem;
                    font-weight:600;
                ">

                    Clearing Issue / Updates

                </h4>

                <!-- MESSAGE -->

                <div class="form-group"
                    style="margin-bottom:1rem;">

                    <label class="form-label">

                        Message

                    </label>

                    <textarea
                        class="form-control"
                        rows="3"
                        placeholder="Resolution steps..."
                    ></textarea>

                </div>

                <!-- GRID -->

               <div style="
    display:grid;
    grid-template-columns:1fr 1fr 1fr;
                ">

                    <!-- ASSIGN -->

                    <div class="form-group">

                        <label class="form-label">

                            Assign To

                        </label>

<select class="form-select" name="assign_to">

    <option value="">-- Select Employee --</option>

    <?php
    $stmt = $pdo->query("
        SELECT id, first_name, last_name
        FROM employees
        WHERE status = 1
        ORDER BY first_name ASC
    ");

    while($emp = $stmt->fetch(PDO::FETCH_ASSOC)){
    ?>
        <option value="<?= $emp['id']; ?>">
            <?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?>
        </option>
    <?php } ?>

</select>

                    </div>

                    <!-- STATUS -->

                    <div class="form-group">

                        <label class="form-label">

                            Update Status

                        </label>

                        <select class="form-select">

                            <option>
                                Open
                            </option>

                            <option>
                                In Progress
                            </option>

                            <option>
                                Resolved
                            </option>

                            <option>
                                Closed
                            </option>

                        </select>

                    </div>

                </div>

            </div>

            <!-- BUTTON -->

            <button
                class="btn"
                style="
                    width:100%;
                    background:#17233c;
                    color:white;
                    height:48px;
                    border:none;
                    border-radius:8px;
                    font-weight:600;
                    font-size:1rem;
                "
            >

                Update Ticket

            </button>

        </div>

    </div>

</div>
<!-- CREATE TICKET MODAL -->

<div id="ticketModal" class="modal">

    <div class="modal-card" style="width:500px;">

        <div class="modal-header" style="
            background:var(--primary);
            color:white;
            padding:1rem;
            display:flex;
            justify-content:space-between;
            align-items:center;
        ">

            <h3 style="margin:0;">
                Create New Ticket
            </h3>

            <i
                class="ri-close-line"
                style="
                    cursor:pointer;
                    font-size:1.5rem;
                "
                onclick="closeModal('ticketModal')"
            ></i>

        </div>

        <div class="modal-body" style="padding:1.5rem;">
 <form
    method="POST"
    action="save-support-ticket.php"
    novalidate
    onsubmit="return validateTicketForm()"
>

            <div class="form-group" style="margin-bottom:1rem;">

                <label class="form-label">
                    Issue Type
                </label>

<select
    class="form-select"
    name="issue_type"
    id="issue_type"
>
                    <option>Quality Issue</option>
                    <option>Fitting/Alteration</option>
                    <option>Delivery Delay</option>
                    <option>Damage</option>
                    <option>Other</option>

                </select>
<div
    id="issue_type_error"
    style="
        color:red;
        font-size:13px;
        margin-top:4px;
    "
></div>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
<div class="form-group" style="margin-bottom:1rem;">

    <label class="form-label">
        Ticket Name
    </label>

   <input
    type="text"
    class="form-control"
    name="ticket_name"
    id="ticket_name"
    >
<div
    id="ticket_name_error"
    style="
        color:red;
        font-size:13px;
        margin-top:4px;
    "
></div>
</div>
                <label class="form-label">
                    Description
                </label>

             <textarea
    class="form-control"
    name="description"
    id="description"
                    rows="3"
                    placeholder="Describe issue..."
                ></textarea>
<div
    id="description_error"
    style="
        color:red;
        font-size:13px;
        margin-top:4px;
    "
></div>
            </div>

<div style="
    display:grid;
    grid-template-columns:1fr 1fr 1fr;
    gap:1rem;
    margin-bottom:1rem;
">

    <!-- PRIORITY -->

    <div class="form-group">

        <label class="form-label">
            Priority
        </label>

      <select
    class="form-select"
    name="priority"
    id="priority"
>

            <option>Low</option>
            <option>Medium</option>
            <option>High</option>
            <option>Critical</option>

        </select>
<div
    id="priority_error"
    style="
        color:red;
        font-size:13px;
        margin-top:4px;
    "
></div>
    </div>

    <!-- COST -->

    <div class="form-group">

        <label class="form-label">
            Cost
        </label>

       <input
    type="number"
    class="form-control"
    name="remediation_cost"
    id="remediation_cost"
    placeholder="0.00"
>
<div
    id="remediation_cost_error"
    style="
        color:red;
        font-size:13px;
        margin-top:4px;
    "
></div>

    </div>

    <!-- STATUS -->

    <div class="form-group">

        <label class="form-label">
            Status
        </label>

        <select
    class="form-select"
    name="status"
    id="status"
>

            <option value="Open">
                Open
            </option>

            <option value="In Progress">
                In Progress
            </option>

            <option value="Resolved">
                Resolved
            </option>

            <option value="Closed">
                Closed
            </option>

        </select>
<div
    id="status_error"
    style="
        color:red;
        font-size:13px;
        margin-top:4px;
    "
></div>
    </div>

</div>
          <button
    type="submit"
    class="btn btn-primary"
    style="width:100%;"
>
    Create Ticket
</button>
</form>
        </div>

    </div>

</div>

<style>

.badge{
    padding:4px 10px;
    border-radius:4px;
    font-size:0.75rem;
    font-weight:600;
}

.badge-danger{
    background:#fee2e2;
    color:#dc2626;
}

.badge-warning{
    background:#fef3c7;
    color:#d97706;
}

.badge-success{
    background:#dcfce7;
    color:#16a34a;
}

.btn-icon{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    padding:6px;
    border-radius:6px;
    cursor:pointer;
    color:#64748b;
}

.btn-icon:hover{
    background:#eef2ff;
    color:#4f46e5;
}

.modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    z-index:1000;
    justify-content:center;
    align-items:center;
}

.modal-card{
    background:white;
    border-radius:12px;
    overflow:hidden;
}

</style>

<script>

function openTicketModal(){

    document.getElementById(
        'ticketModal'
    ).style.display = 'flex';

}

function viewTicket(
    id,
    description,
    status
){

    document.getElementById(
        'view_ticket_no'
    ).innerHTML = id;

    document.getElementById(
        'view_ticket_description'
    ).innerHTML = description;

    document.getElementById(
        'view_ticket_status'
    ).innerHTML = status;
document.querySelector('select[name="assign_to"]').value = "";
    document.getElementById(
        'viewModal'
    ).style.display = 'flex';

}

function raiseBill(
    id,
    amount
){

    document.getElementById(
        'bill_ticket_no'
    ).value = id;

    document.getElementById(
        'bill_amount'
    ).value = amount;

    document.getElementById(
        'billModal'
    ).style.display = 'flex';

}

function closeModal(id){

    document.getElementById(id)
    .style.display = 'none';

}



</script>

<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>

initializeDataTable(
    'supportTable',
    'Support System'
);

</script>
<script>

function validateTicketForm(){

    let valid = true;

    document.getElementById('issue_type_error').innerHTML = '';
    document.getElementById('ticket_name_error').innerHTML = '';
    document.getElementById('description_error').innerHTML = '';
    document.getElementById('priority_error').innerHTML = '';
    document.getElementById('remediation_cost_error').innerHTML = '';
    document.getElementById('status_error').innerHTML = '';

    let issueType =
        document.getElementById('issue_type').value;

    let ticketName =
        document.getElementById('ticket_name').value;

    let description =
        document.getElementById('description').value;

    let priority =
        document.getElementById('priority').value;

    let cost =
        document.getElementById('remediation_cost').value;

    let status =
        document.getElementById('status').value;

    if(issueType == ''){

        document.getElementById(
            'issue_type_error'
        ).innerHTML =
        'Issue Type field is required';

        valid = false;

    }

    if(ticketName.trim() == ''){

        document.getElementById(
            'ticket_name_error'
        ).innerHTML =
        'Ticket Name field is required';

        valid = false;

    }

    if(description.trim() == ''){

        document.getElementById(
            'description_error'
        ).innerHTML =
        'Description field is required';

        valid = false;

    }

    if(priority == ''){

        document.getElementById(
            'priority_error'
        ).innerHTML =
        'Priority field is required';

        valid = false;

    }

    if(cost == ''){

        document.getElementById(
            'remediation_cost_error'
        ).innerHTML =
        'Cost field is required';

        valid = false;

    }

    if(status == ''){

        document.getElementById(
            'status_error'
        ).innerHTML =
        'Status field is required';

        valid = false;

    }

    return valid;

}

</script>
<?php include 'includes/footer.php'; ?>

<?php if(isset($_GET['success'])) : ?>

<script>

Swal.fire({
    icon:'success',
    title:'Success',
    text:'Ticket Created Successfully',
    confirmButtonColor:'#4f46e5'
});

if(window.history.replaceState){

    window.history.replaceState(
        null,
        null,
        window.location.pathname
    );

}

</script>

<?php endif; ?>