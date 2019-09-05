<?php

/* +----------------------------------+ */
/* |          G-Code Rotator          | */
/* |            index.php             | */
/* |   (c)copyright nitram147 2019    | */
/* +----------------------------------+ */

/*
Purpose of this tool is conversion (by rotation, move and scale) of all X,Y coordinates in G-Code file to align with "new" axises.
Board (for example PCB) can be placed in any starting position, rotated by any angle and scaled by any scale factor.
Two points are taken as input - ideally 2 most distant point that can be precisely measured on board. Original means their original position in G-Code, New means their real position on board. From these two position are angle, distance and scale factor calculated.
Whole G-Code is converted and returned to download.
*/

/*
Test entry (to test if conversion work correctly enable DEBUG by setting it as "true", output should be follows (new point 2 and rotated point2 coordinates should be equal (after rounding of course)):

Original Point1 x1o = 0 ; y1o = 0 ; New x1n = 0.5 ; y1n = 0.25 ;
Original Point2 x2o = -1 ; y2o = 1 ; New x2n = 0.5 ; y2n = 0.9571067811865475244 ;
Distance old = 1.4142135623731 ; Distance new = 0.70710678118655 ; Scale factor = 0.5 ;
Moved Point2 x2m = 0 ; y2m = 1.4142135623731 ;
Lens: opp_len = 1.0823922002924 ; leg_len = 1.4142135623731 ; hyp_len = 1.4142135623731 ;
Cos angle value = 0.70710678118655 ;
Angle rad = -0.78539816339745 ; deg = -45 ;
Rotated point2 x = 0.5 ; y = 0.957107 ;

*/

const DEBUG = false;
const ROUND_PRECISION = 4;

if(!empty($_POST['convert'])){

	$x1o = $_POST['x1o'];
	$x2o = $_POST['x2o'];
	$x1n = $_POST['x1n'];
	$x2n = $_POST['x2n'];
	
	$y1o = $_POST['y1o'];
	$y2o = $_POST['y2o'];
	$y1n = $_POST['y1n'];
	$y2n = $_POST['y2n'];

	$scale = false;
	if($_POST['scale'] == "true") $scale = true;

	$gcode_file_name = basename($_FILES['gcode_file']['name']);
	$gcode_file_content = file_get_contents($_FILES['gcode_file']['tmp_name']);

	if(!is_numeric($x1o) || !is_numeric($x2o) || !is_numeric($x1n) || !is_numeric($x2n) || !is_numeric($y1o) || !is_numeric($y2o) || !is_numeric($y1n) || !is_numeric($y2n)){
		echo '<center><h2>All point values must be numeric!<h2><a href="">Try again!</a>';
		exit();
	}

	if($_FILES['gcode_file']['name'] == "" || $_FILES['gcode_file']['size'] == 0){
		echo '<center><h2>Please upload a file, which you want to convert!<h2><a href="">Try again!</a>';
		exit();
	}

	class Convertor{
		
		private $scale_factor, $angle, $x1o, $y1o, $dx1, $dy1;

		function __construct($x1o, $y1o, $x1n, $y1n, $x2o, $y2o, $x2n, $y2n){
			
			if(DEBUG) echo "Original Point1 x1o = $x1o ; y1o = $y1o ; New x1n = $x1n ; y1n = $y1n ;<br>";
			if(DEBUG) echo "Original Point2 x2o = $x2o ; y2o = $y2o ; New x2n = $x2n ; y2n = $y2n ;<br>";

			//calculate distances (original, new)
			$dist_o = sqrt((($x1o - $x2o)**2) + (($y1o - $y2o)**2));
			$dist_n = sqrt((($x1n - $x2n)**2) + (($y1n - $y2n)**2));

			//scale factor - used after rotation and move
			$scale_factor = $dist_n / $dist_o;
			$this->scale_factor = $scale_factor;

			if(DEBUG) echo "Distance old = $dist_o ; Distance new = $dist_n ; Scale factor = $scale_factor ;<br>";

			//backup new point 1 coordinates
			$orig_x1n = $x1n;
			$orig_y1n = $y1n;

			//inverse scale point 1 & 2 to original scale
			$x1n *= (1/$scale_factor); $y1n *= (1/$scale_factor);
			$x2n *= (1/$scale_factor); $y2n *= (1/$scale_factor);

			//calculate distance difference between new and original point 1
			$dx1 = ($x1n - $x1o);
			$dy1 = ($y1n - $y1o);
			
			//move new point 2, that way, that new point 1 will align with original point 1 coordinates
			$x2m = $x2n - $dx1;
			$y2m = $y2n - $dy1;

			if(DEBUG) echo "Moved Point2 x2m = $x2m ; y2m = $y2m ;<br>";

			//calculate lengths of triangle (original point 1, new point 2, moved point 2) sides
			$opp_len = sqrt((($x2m - $x2o)**2) + (($y2m - $y2o)**2));
			$leg_len = sqrt((($x2o - $x1o)**2) + (($y2o - $y1o)**2));
			$hyp_len = sqrt((($x2m - $x1o)**2) + (($y2m - $y1o)**2));

			if(DEBUG) echo "Lens: opp_len = $opp_len ; leg_len = $leg_len ; hyp_len = $hyp_len ;<br>";

			//calculate cosine of angle corresponding to vertex original point 1
			$cos_angle = ((($opp_len**2) - ($leg_len**2) - ($hyp_len**2)) / (-2 * $leg_len * $hyp_len));
			//calculate angle from its cosine
			$angle = acos($cos_angle);
			//in case that angle is 180 degress (PI) our "triangle" dont exist so inverse cosine cant be calculated (would be NAN)
			//in this case fill 180 (PI) as our angle
			if(round($cos_angle, 5) == -1) $angle = M_PI;

			if(DEBUG) echo "Cos angle value = $cos_angle ; <br>";

			//calculate line equation in (y = ax + b) format
			$le_dx = $x2o - $x1o;
			$le_dy = $y2o - $y1o;
			$le_a = $le_dy / $le_dx;
			$le_b = $y2o - ($le_a * $x2o);

			//calculate moved point 2 y coordinate in case that it lays on (original poin 2 - original point 1) line
			$le_y2m = $le_a * $x2m + $le_b;
			//find where real y coordinate of moved point 2 is and find a correct direction of rotation
			if($y2m > $le_y2m) $angle *= -1;

			$this->angle = $angle;

			if(DEBUG) echo "Angle rad = $angle ; deg = ".rad2deg($angle)." ;<br>";

			$this->x1o = $x1o; $this->y1o = $y1o;
			$this->dx1 = $dx1; $this->dy1 = $dy1;

			if(DEBUG){

				$result = $this->convert_point($x2o, $y2o);
				if(DEBUG) echo "Rotated point2 x = ".$result["x"]." ; y = ".$result["y"]." ;<br>";

			}

		}

		function convert_point($x, $y){

			//we want to rotate around original point 1 not origin, so we have to substract it
			$tmp_x = $x - $this->x1o;
			$tmp_y = $y - $this->y1o;

			//rotate our point
			$tmp_n_x = $tmp_x * cos($this->angle) - $tmp_y * sin($this->angle);
			$tmp_n_y = $tmp_y * cos($this->angle) + $tmp_x * sin($this->angle);

			//add original point 1 coordinates (because we had substracted them earlier)
			//add distance difference to new point 1 (so we move it to desired location)
			//scale it to desired scale
			$tmp_x = ($tmp_n_x + $this->x1o + $this->dx1) * $this->scale_factor;
			$tmp_y = ($tmp_n_y + $this->y1o + $this->dy1) * $this->scale_factor;

			//round coordinates to required 
			$tmp_x = round($tmp_x, ROUND_PRECISION);
			$tmp_y = round($tmp_y, ROUND_PRECISION);

			$result = array();
			$result["x"] = $tmp_x;
			$result["y"] = $tmp_y;
			return $result;			

		}

	}


	class GCodeParser{
		
		//return X in 1st matching group, Y in 2nd matching group
		const CoordinatesLineRegex = '/^G[0-9]{1,3}.*\s+X(-{0,1}[0-9]+\.[0-9]+)\s+Y(-{0,1}[0-9]+\.[0-9]+).*$/';

		private $convertor;

		function __construct($convertor){
			
			$this->convertor = $convertor;

		}
		
		//return converted line
		function parse_and_convert_line($line){
			
			if(!preg_match(self::CoordinatesLineRegex, $line, $match)) return $line;
			$x_old = $match[1]; $y_old = $match[2];

			$result = $this->convertor->convert_point($x_old, $y_old);

			$x_new = $result["x"]; $y_new = $result["y"];
						
			$line = str_replace("X".$x_old, "X".$x_new, $line);
			$line = str_replace("Y".$y_old, "Y".$y_new, $line);

			return $line;

		}

	}

	//construct
	$convertor = new Convertor($x1o, $y1o, $x1n, $y1n, $x2o, $y2o, $x2n, $y2n);
	$gcode_parser = new GCodeParser($convertor);

	$new_gcode = "(G-Code coordinates converted by Nitram147 G-Code rotator)\n";
	$new_gcode .= "(Original point 1 [x = $x1o, y = $y1o], Original point 2 [x = $x2o, y = $y2o])\n";
	$new_gcode .= "(New point 1 [x = $x1n, y = $y1n], New point 2 [x = $x2n, y = $y2n])\n";

	$lines = explode(PHP_EOL, $gcode_file_content);
	foreach ($lines as $key => $line) {
		$new_gcode .= $gcode_parser->parse_and_convert_line($line);
	}

	//return header to automaticly save output as file
	header("Content-type: text/plain");
	header("Content-Disposition: attachment; filename=rotated_".$gcode_file_name);

	//print converted g-code with windows like EOL  = \r\n (programs like Mach3 need this type of EOL)
	echo preg_replace('/\r|\r\n|\n/', "\r\n", $new_gcode);

	exit();

}

?>

<html>
	
	<title>Nitram147 - Automatic G-Code rotator</title>
	
	<style>
	
		body{
			margin: 0 auto;
			text-align: center;
		}
	
		.heading{
			margin-top: 150px;
		}
	
		.input_text {
			border-radius: 8px;
			width: 250px;
			height: 32px;
			padding: 0 8px;
			margin: 4px 8px;
		}
	
		.point_text{
			margin-bottom: 4px;
		}

		.checkbox{
			cursor: pointer;
		}

		.input_file{
			margin: 24px 0 12px 0;
		}

		.input_submit{
			margin: 12px 0;
			width: 250px;
			height: 32px;
		}

	</style>

	<script>

		function invert_checkbox(){
			var checkbox = document.getElementById('scale');
			checkbox.checked = !(checkbox.checked);
		}

	</script>

	<h1 class="heading">Nitram147 - Automatic G-Code rotator</h1>
	
	<form action="#" method="post" enctype="multipart/form-data">

		<h2 class="point_text">Point 1:</h2>
		<input class="input_text" type="text" name="x1o" placeholder="Original X1" required="">
		<input class="input_text" type="text" name="y1o" placeholder="Original Y1" required=""><br>
		<input class="input_text" type="text" name="x1n" placeholder="New X1" required="">
		<input class="input_text" type="text" name="y1n" placeholder="New Y1" required=""><br>

		<h2 class="point_text">Point 2:</h2>
		<input class="input_text" type="text" name="x2o" placeholder="Original X2" required="">
		<input class="input_text" type="text" name="y2o" placeholder="Original Y2" required=""><br>
		<input class="input_text" type="text" name="x2n" placeholder="New X2" required="">
		<input class="input_text" type="text" name="y2n" placeholder="New Y2" required=""><br>

		<br>
		<input class="checkbox" type="checkbox" name="scale" id=scale value="true" checked><b onclick="invert_checkbox();" class="checkbox">Scale points based on new distance</b><br>

		<input class="input_file" type="file" name="gcode_file" zidan=""><br>

		<input class="input_submit" type="submit" name="convert" value="Convert G-Code">

	</form>	

</html>