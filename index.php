<html> 
    <head>
        <title> I love BUU </title>
        <?php
            $host = "localhost";
            $dbname = "s67160155";
            $username = "s67160155";
            $password = "DU5HX8mk";

            $con = mysqli_connect($host, $username, $password, $dbname);

            if (!$con){
                die("Connect Failed". mysqli_connect_error());
            } 
            else{
                echo "Connect Successfull!";
            }
        ?>
    </head>
    <body bgcolor >
        <center>
        Hello World. I am Kittithat
        <br>
        <br>
        <img src = HunSen.jpg>
        <br>
        <table>
            <tr>
                <td> Nmae </td>
                <td> Salary </td>
            </tr>
        <?php
            $sql = "SELECT * FROM employees WHERE 1";
            $query = mysqli_query($con, $sql);
            while($row = mysqli_fetch_assoc(result: $query)){
            ?>  
                <tr>
                    <td> <?php echo $row["emp_name"];?></td>
                    <td> <?php echo $row["salary"];?></td>
        <?php
            }
        ?>
    </body> 
</html>