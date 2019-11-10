<?php
echo("<table>");
for($i=0;$i<6;$i++) {
	echo("<tr>");
	for($j=0;$j<6;$j++) {
		echo("<td style='width: 70px;'>". $matrix[$i][$j]  . "</td>");
	}	
	echo("</tr>");
}
echo("</table>");

echo("<br><span> Estados </span><br>");

for($i=0;$i<6;$i++) {
	echo("<span>". $states[$i] . "</span>");
	echo("<br>");	
}
?>

