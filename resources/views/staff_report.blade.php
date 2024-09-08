<!DOCTYPE html>
<html>
<head>
    <title>Staff Report</title>
</head>
<body>
<h1>Staff Report</h1>
<p>Start Date: {{ $start_date }}</p>
<p>End Date: {{ $end_date }}</p>
<table>
    <thead>
    <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Salary</th>
        <th>Salary Frequency</th>
        <th>Hire Date</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($employees as $employee)
        <tr>
            <td>{{ $employee->name }}</td>
            <td>{{ $employee->email }}</td>
            <td>{{ $employee->role->name }}</td>
            <td>{{ $employee->salary }}</td>
            <td>{{ $employee->salary_frequency }}</td>
            <td>{{ $employee->hire_date ?? '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
