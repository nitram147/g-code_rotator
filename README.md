# G-Code Rotator
PHP tool for conversion of X,Y coordinates in G-Code file.

---

Purpose of this tool is conversion (by rotation, move and scale) of all X,Y coordinates in G-Code file to align with "new" axises.  
Board (for example PCB) can be placed in any starting position, rotated by any angle and scaled by any scale factor.  
Two points are taken as input - ideally 2 most distant point that can be precisely measured on board. Original means their original position in G-Code, New means their real position on board. From these two position are angle, distance and scale factor calculated.  
Whole G-Code is converted and returned to download.

---

Test entry (to test if conversion work correctly enable DEBUG by setting it as "true", output should be follows (new point 2 and rotated point2 coordinates should be equal (after rounding of course)):

Original Point1 x1o = 0 ; y1o = 0 ; New x1n = 0.5 ; y1n = 0.25 ;  
Original Point2 x2o = -1 ; y2o = 1 ; New x2n = 0.5 ; y2n = 0.9571067811865475244 ;  
Distance old = 1.4142135623731 ; Distance new = 0.70710678118655 ; Scale factor = 0.5 ;  
Moved Point2 x2m = 0 ; y2m = 1.4142135623731 ;  
Lens: opp_len = 1.0823922002924 ; leg_len = 1.4142135623731 ; hyp_len = 1.4142135623731 ;  
Cos angle value = 0.70710678118655 ;  
Angle rad = -0.78539816339745 ; deg = -45 ;  
Rotated point2 x = 0.5 ; y = 0.957107 ;  

---
