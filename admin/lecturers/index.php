<?php

session_start();

/* =====================================================
   ADMIN LOGIN CHECK
===================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../../auth/login.php");
    exit();
}


/* =====================================================
   DATABASE CONNECTION
===================================================== */

require_once "../../config/database.php";


/* =====================================================
   GET LECTURERS
===================================================== */

$lecturers = [];

$sql = "
    SELECT
        l.id,
        l.lecturer_id,
        l.full_name,
        l.email,
        l.phone,
        l.designation,
        d.department_name
    FROM lecturers l
    LEFT JOIN departments d
        ON l.department_id = d.id
    ORDER BY l.id DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $lecturers[] = $row;

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Lecturers - Admin</title>


    <!-- ADMIN CSS -->

    <link
        rel="stylesheet"
        href="../../css/admin.css?v=10"
    >


    <style>

        /* =================================================
           PAGE
        ================================================= */

        .page-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;

        }


        .page-header h2 {

            margin: 0;

            color: #172554;

        }


        .page-header p {

            margin-top: 6px;

            color: #6b7280;

        }


        /* =================================================
           ADD BUTTON
        ================================================= */

        .btn-add {

            background: #172554;

            color: #ffffff;

            text-decoration: none;

            padding: 10px 18px;

            border-radius: 6px;

            font-weight: 600;

            transition: 0.2s;

        }


        .btn-add:hover {

            background: #1e3a8a;

        }


        /* =================================================
           TABLE CARD
        ================================================= */

        .table-card {

            background: #ffffff;

            border-radius: 10px;

            padding: 20px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            overflow-x: auto;

        }


        /* =================================================
           SEARCH
        ================================================= */

        .search-box {

            margin-bottom: 18px;

        }


        .search-box input {

            width: 100%;

            max-width: 400px;

            padding: 11px 13px;

            border: 1px solid #d1d5db;

            border-radius: 6px;

            font-size: 14px;

        }


        .search-box input:focus {

            outline: none;

            border-color: #172554;

            box-shadow:
                0 0 0 2px
                rgba(23, 37, 84, 0.1);

        }


        /* =================================================
           TABLE
        ================================================= */

        .lecturer-table {

            width: 100%;

            border-collapse: collapse;

            min-width: 950px;

        }


        .lecturer-table th {

            background: #172554;

            color: #ffffff;

            padding: 12px;

            text-align: left;

            font-size: 14px;

        }


        .lecturer-table td {

            padding: 12px;

            border-bottom:
                1px solid #e5e7eb;

            color: #374151;

            font-size: 14px;

        }


        .lecturer-table tr:hover {

            background: #f8fafc;

        }


        /* =================================================
           BADGE
        ================================================= */

        .department-badge {

            display: inline-block;

            background: #eff6ff;

            color: #1d4ed8;

            padding: 5px 9px;

            border-radius: 15px;

            font-size: 12px;

            font-weight: 600;

        }


        /* =================================================
           ACTION BUTTONS
        ================================================= */

        .action-buttons {

            display: flex;

            gap: 7px;

        }


        .btn-view,
        .btn-edit {

            text-decoration: none;

            padding: 6px 10px;

            border-radius: 5px;

            font-size: 12px;

            font-weight: 600;

        }


        .btn-view {

            background: #e0f2fe;

            color: #0369a1;

        }


        .btn-edit {

            background: #fef3c7;

            color: #92400e;

        }


        .btn-view:hover {

            background: #bae6fd;

        }


        .btn-edit:hover {

            background: #fde68a;

        }


        /* =================================================
           EMPTY MESSAGE
        ================================================= */

        .empty-message {

            text-align: center;

            padding: 40px;

            color: #6b7280;

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 700px) {

            .page-header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;

            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- =================================================
         SIDEBAR
    ================================================= -->

    <?php

    include "../../includes/admin_sidebar.php";

    ?>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <div class="main-content">


        <!-- HEADER -->

        <?php

        include "../../includes/admin_header.php";

        ?>


        <!-- CONTENT -->

        <div class="content">


            <!-- =================================================
                 PAGE HEADER
            ================================================= -->

            <div class="page-header">

                <div>

                    <h2>Lecturers</h2>

                    <p>
                        Manage college lecturers and their accounts.
                    </p>

                </div>


                <a
                    href="add.php"
                    class="btn-add"
                >
                    + Add Lecturer
                </a>

            </div>


            <!-- =================================================
                 TABLE CARD
            ================================================= -->

            <div class="table-card">


                <!-- SEARCH -->

                <div class="search-box">

                    <input
                        type="text"
                        id="lecturerSearch"
                        placeholder="Search by name, lecturer ID, email or department..."
                    >

                </div>


                <?php if (count($lecturers) > 0): ?>


                    <table
                        class="lecturer-table"
                        id="lecturerTable"
                    >

                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Lecturer ID
                                </th>

                                <th>
                                    Full Name
                                </th>

                                <th>
                                    Email
                                </th>

                                <th>
                                    Phone
                                </th>

                                <th>
                                    Department
                                </th>

                                <th>
                                    Designation
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php

                            $count = 1;

                            foreach ($lecturers as $lecturer):

                            ?>


                                <tr>


                                    <!-- NUMBER -->

                                    <td>

                                        <?php

                                        echo $count++;

                                        ?>

                                    </td>


                                    <!-- LECTURER ID -->

                                    <td>

                                        <strong>

                                            <?php

                                            echo htmlspecialchars(
                                                $lecturer["lecturer_id"]
                                            );

                                            ?>

                                        </strong>

                                    </td>


                                    <!-- NAME -->

                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $lecturer["full_name"]
                                        );

                                        ?>

                                    </td>


                                    <!-- EMAIL -->

                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $lecturer["email"] ?? "-"
                                        );

                                        ?>

                                    </td>


                                    <!-- PHONE -->

                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $lecturer["phone"] ?? "-"
                                        );

                                        ?>

                                    </td>


                                    <!-- DEPARTMENT -->

                                    <td>

                                        <?php

                                        if (
                                            !empty(
                                                $lecturer[
                                                    "department_name"
                                                ]
                                            )
                                        ):

                                        ?>

                                            <span
                                                class="department-badge"
                                            >

                                                <?php

                                                echo htmlspecialchars(
                                                    $lecturer[
                                                        "department_name"
                                                    ]
                                                );

                                                ?>

                                            </span>

                                        <?php

                                        else:

                                            echo "-";

                                        endif;

                                        ?>

                                    </td>


                                    <!-- DESIGNATION -->

                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $lecturer[
                                                "designation"
                                            ] ?? "-"
                                        );

                                        ?>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td>

                                        <div
                                            class="action-buttons"
                                        >


                                            <a
                                                href="view.php?id=<?php echo (int)$lecturer["id"]; ?>"
                                                class="btn-view"
                                            >
                                                View
                                            </a>


                                            <a
                                                href="edit.php?id=<?php echo (int)$lecturer["id"]; ?>"
                                                class="btn-edit"
                                            >
                                                Edit
                                            </a>
                                            <a
                                                href="delete.php?id=<?php echo (int)$lecturer['id']; ?>"
                                                class="btn-delete"
                                                 onclick="return confirm(
                                                 'Are you sure you want to delete this lecturer? This will also remove the lecturer login account.'
                                                  );"
                                            >
                                                Delete
                                            </a>

                                        </div>

                                    </td>


                                </tr>


                            <?php

                            endforeach;

                            ?>

                        </tbody>

                    </table>


                <?php else: ?>


                    <div class="empty-message">

                        <h3>
                            No Lecturers Found
                        </h3>

                        <p>
                            No lecturer accounts have been added yet.
                        </p>

                        <br>

                        <a
                            href="add.php"
                            class="btn-add"
                        >
                            + Add First Lecturer
                        </a>

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


</div>


<!-- =====================================================
     SEARCH SCRIPT
===================================================== -->

<script>

const searchInput =
    document.getElementById("lecturerSearch");

const table =
    document.getElementById("lecturerTable");


if (searchInput && table) {

    searchInput.addEventListener(
        "input",
        function () {

            const search =
                this.value
                    .toLowerCase()
                    .trim();


            const rows =
                table
                    .querySelectorAll(
                        "tbody tr"
                    );


            rows.forEach(function (row) {

                const text =
                    row.textContent
                        .toLowerCase();


                if (
                    text.includes(search)
                ) {

                    row.style.display = "";

                } else {

                    row.style.display = "none";

                }

            });

        }
    );

}

</script>


</body>

</html>