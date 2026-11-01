{{-- --- Core Libraries (Always Included) --- --}}

{{-- Bootstrap --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

{{-- jQuery --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js" defer></script>

{{-- Bootstrap JavaScript --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer></script>


{{-- --- Optional Libraries --- --}}

{{-- DataTables --}}
@if(in_array('datatables', $libraries ?? [], true))
  <link rel="stylesheet" href="https://cdn.datatables.net/2.3.5/css/dataTables.bootstrap5.min.css">
  <script src="https://cdn.datatables.net/2.3.5/js/dataTables.min.js" defer></script>
  <script src="https://cdn.datatables.net/2.3.5/js/dataTables.bootstrap5.min.js" defer></script>
@endif

{{-- SweetAlert2 --}}
@if(in_array('sweetalert2', $libraries ?? [], true))
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js" defer></script>
@endif

{{-- Flatpickr --}}
@if(in_array('flatpickr', $libraries ?? [], true))
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr@4/dist/flatpickr.min.js" defer></script>
@endif

{{-- Tom Select --}}
@if(in_array('tomselect', $libraries ?? [], true))
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2/dist/css/tom-select.bootstrap5.min.css">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2/dist/js/tom-select.complete.min.js" defer></script>
@endif

{{-- Chart.js --}}
@if(in_array('charts', $libraries ?? [], true))
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>
@endif


{{-- --- Ticketio Assets (Always Included) --- --}}
<link rel="stylesheet" href="/assets/css/app.css">
<script src="/assets/js/app.js" defer></script>
