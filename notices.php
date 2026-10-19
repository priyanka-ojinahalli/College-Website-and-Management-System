<?php

require_once "config/database.php";

/* Get only active notices */

$result = $conn->query(
    "SELECT *
     FROM notices
     WHERE status = 'active'
     ORDER BY notice_date DESC, id DESC"
);

?>

<?php include "includes/public_header.php"; ?>


<!-- ================= NOTICES PAGE ================= -->

<section class="section">

    <div class="section-title">

        <h2>College Notices</h2>

        <p>
            Important announcements and information
            for students and visitors
        </p>

    </div>


    <div class="notice-list">


        <?php if ($result && $result->num_rows > 0): ?>


            <?php while ($notice = $result->fetch_assoc()): ?>


                <!-- ================= NOTICE ================= -->

                <div
                    class="notice"
                    id="notice-<?php echo (int)$notice["id"]; ?>"
                >


                    <!-- NOTICE TITLE -->

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $notice["title"]
                        );
                        ?>

                    </strong>


                    <!-- NOTICE DESCRIPTION -->

                    <p>

                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                $notice["description"]
                            )
                        );
                        ?>

                    </p>


                    <!-- NOTICE DATE -->

                    <p class="notice-date">

                        Date:

                        <?php
                        echo date(
                            "d M Y",
                            strtotime(
                                $notice["notice_date"]
                            )
                        );
                        ?>

                    </p>


                    <!-- POSTED BY -->

                    <p class="notice-date">

                        Posted by:

                        <?php

                        if (
                            !empty($notice["created_role"])
                        ) {

                            echo htmlspecialchars(
                                ucfirst(
                                    $notice["created_role"]
                                )
                            );

                        } else {

                            /*
                             * Old notices were uploaded
                             * only by Admin.
                             */

                            echo "Admin";

                        }

                        ?>

                    </p>


                </div>


            <?php endwhile; ?>


        <?php else: ?>


            <!-- ================= NO NOTICES ================= -->

            <div class="notice">

                <strong>
                    No notices available
                </strong>

                <p>
                    There are currently no active
                    college notices.
                </p>

            </div>


        <?php endif; ?>


    </div>

</section>


<!-- ================= INFORMATION ================= -->

<section class="section notice-section">

    <div class="section-title">

        <h2>Stay Updated</h2>

        <p>
            Please check this page regularly for
            important announcements from the
            college administration.
        </p>

    </div>

</section>


<?php include "includes/public_footer.php"; ?>


<!-- ================= NOTICE LINK STYLING ================= -->

<style>

.notice {
    scroll-margin-top: 100px;
}


/*
 * Highlight the notice when the user
 * arrives through a homepage link.
 */

.notice:target {

    outline: 3px solid #2563eb;

    background: #eff6ff;

    transition: 0.3s;

}

</style>