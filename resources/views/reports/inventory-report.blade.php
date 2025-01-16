<!DOCTYPE html>
<html>
<head>
    <title> {{ $month }} {{ $year }} Report</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid black;
            text-align: center;
            padding: 8px;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h1>{{ $month }} {{ $year }} Report</h1>
    <table>
        <thead>
            <tr>
                <th>Day</th>
                @foreach ($yearsKeys as $year)
                    <th>Year {{ $year }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($reportData as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                @foreach ($yearsKeys as $year)
                    <td>{{ $row[$year] }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
