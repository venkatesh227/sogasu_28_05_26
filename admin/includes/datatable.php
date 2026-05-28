<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<style>

.dt-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:10px;
}

.dt-left{
    display:flex;
    align-items:center;
    gap:10px;
}

.dt-right{
    display:flex;
    align-items:center;
}

.dataTables_length{
    display:flex;
    align-items:center;
    gap:5px;
}

.dataTables_length label{
    display:flex !important;
    align-items:center;
    gap:6px;
    white-space:nowrap;
}

.dataTables_length select{
    height:32px;
    padding:4px 8px;
}

.dataTables_filter input{
    height:32px;
    margin-left:5px;
}

.dt-buttons{
    display:flex;
    gap:10px;
}

</style>

<script>

function initializeDataTable(tableId, title='Export Data', statusColumn=null) {

    if ($.fn.DataTable.isDataTable('#' + tableId)) {
        $('#' + tableId).DataTable().destroy();
    }

    $('#' + tableId).DataTable({

        pageLength: 10,
        responsive: true,
        order: [],

        dom: "<'dt-top'<'dt-left'lB><'dt-right'f>>rtip",

        buttons: [
            {
                extend: 'excelHtml5',
                text: 'Export Excel',
                title: title,
                className: 'dt-button',
                exportOptions: {
                    columns: ':not(:last-child)',
                    format: {
                        body: function(data, row, column, node) {
                            if (statusColumn !== null && column === statusColumn) {
                                return $(node).find('input').is(':checked') ? 'Active' : 'Inactive';
                            }
                            return $(node).text().trim();
                        }
                    }
                }
            },
            {
                extend: 'pdfHtml5',
                text: 'Export PDF',
                title: title,
                className: 'dt-button',
                orientation: 'landscape',
                exportOptions: {
                    columns: ':not(:last-child)',
                    format: {
                        body: function(data, row, column, node) {
                            if (statusColumn !== null && column === statusColumn) {
                                return $(node).find('input').is(':checked') ? 'Active' : 'Inactive';
                            }
                            return $(node).text().trim();
                        }
                    }
                },
                customize: function(doc) {
                    doc.styles.tableHeader = {
                        fillColor: '#6366f1',
                        color: 'white',
                        bold: true,
                        alignment: 'center',
                        fontSize: 10
                    };
                    doc.defaultStyle.fontSize = 9;
                    doc.content[1].alignment = 'center';
                }
            }
        ]
    });
}

</script>