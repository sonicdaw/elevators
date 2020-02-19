<?php
$num_of_floors = intval($_GET["floor"]);
$num_of_elevators = intval($_GET["elevator"]);
$field_height = intval($_GET["height"]);
if($num_of_floors == 0) $num_of_floors = 4;
if($num_of_elevators == 0) $num_of_elevators = 4;
if($field_height == 0) $field_height = 600;
?><html>
<head>
<title>Elevators Proto</title>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
</head>
<body><CENTER>
<a href="http://entatonic.net/elevators/index.php">4x4</a> 
<a href="http://entatonic.net/elevators/index.php?floor=8&elevator=4">4x8</a> 
<a href="http://entatonic.net/elevators/index.php?floor=7&elevator=2">2x7</a> 
<a href="http://entatonic.net/elevators/index.php?floor=10&elevator=10">6x10</a> 
<a href="http://entatonic.net/elevators/index.php?floor=16&elevator=10">10x16</a><br>
<!--[if IE]><script type="text/javascript" src="excanvas.js"></script><![endif]-->
<canvas id="cvs" width="300" height="<?php echo $field_height ?>"></canvas>
<script type="text/javascript">
window.onload = function() {

  const WIDTH = 300;
  const HEIGHT = <?php echo $field_height ?>;
  const LEFT_OFFSET = 10;
  const TOP_OFFSET = 10;
  var FIELD_WIDTH = 280;
  var FIELD_HEIGHT = HEIGHT - TOP_OFFSET * 2;
  const NUM_OF_PEOPLE = 1000;
  const DEFAULT_FONT = "bold 8pt 'Times New Roman'";

  var time_hour = 6;
  var time_minute = 0;
  var time_sec = 0;
  const time_minute_max = 60;
  const time_sec_max = 50;

  var game_over = false;

  // million dollar
  const BUILDING_VALUE_MAX = 10000;
  var building_value = BUILDING_VALUE_MAX;
  const BUILDING_VALUE_WIDTH = 20;
  FIELD_WIDTH = FIELD_WIDTH - BUILDING_VALUE_WIDTH;

  var counter = 0;
  const NUM_OF_ELEVATORS = <?php echo $num_of_elevators ?>;
  const NUM_OF_FLOORS = <?php echo $num_of_floors ?>;

  const ELEVATOR_WIDTH = FIELD_WIDTH / NUM_OF_ELEVATORS;
  const ELEVATOR_HEIGHT = FIELD_HEIGHT / NUM_OF_FLOORS;

  var elevator_y =  new Array(NUM_OF_ELEVATORS);
  var elevator_vy =  new Array(NUM_OF_ELEVATORS);
  var elevator_vvy =  new Array(NUM_OF_ELEVATORS);
  var elevator_target_floor =  new Array(NUM_OF_ELEVATORS);
  var elevator_ride_on = new Array(NUM_OF_ELEVATORS);
  for(var i = 0; i < NUM_OF_ELEVATORS; i++)
  {
    elevator_target_floor[i] = 1;  // ground floor
    elevator_y[i] = getFloorY(elevator_target_floor[i]);
    elevator_vy[i] = 1;
    elevator_vvy[i] = 0;
    elevator_ride_on[i] = false;
  }

  var person_in_field = new Array(NUM_OF_PEOPLE);
  var person_x =  new Array(NUM_OF_PEOPLE);
  var person_current_floor =  new Array(NUM_OF_PEOPLE);
  var person_target_floor =   new Array(NUM_OF_PEOPLE);
  var person_ride_on = new Array(NUM_OF_PEOPLE);
  var person_ride_on_elevator = new Array(NUM_OF_PEOPLE);
  var person_near_elevator_num = new Array(NUM_OF_PEOPLE);
  var person_angry_gauge = new Array(NUM_OF_PEOPLE);
  var person_offset = -11;
  var person_size = new Array(NUM_OF_PEOPLE);

  for(var i = 0; i < NUM_OF_PEOPLE; i++)
  {
    person_in_field[i] = false;
    person_x[i] = -1;
    person_current_floor[i] = 4;	// gound floor
    person_target_floor[i] = 1;
    person_ride_on[i] = false;
    person_ride_on_elevator[i] = -1;
    person_near_elevator_num[i] = 0;
    person_angry_gauge[i] = 0;
    person_size[i] = 1;
  }

  const NUM_OF_ARRIVED = 20;
  const ARRIVED_COUNTER_MAX = 200;
  var arrived_x = new Array(NUM_OF_ARRIVED);
  var arrived_y = new Array(NUM_OF_ARRIVED);
  var arrived_counter = new Array(NUM_OF_ARRIVED);

  for(var i = 0; i < NUM_OF_ARRIVED; i++)
  {
    arrived_x[i] = 0;
    arrived_y[i] = 0;
    arrived_counter[i] = 0;
  }

  var mouseX, mouseY;
  var touchX = 0;
  var touchY = 0;
  var touchViewCounter = 0;
  var touchHoldElevator = -1;
  var timer;
  var canvas = document.getElementById('cvs');
  if (!canvas.getContext) {
    return false;
  }

  var ctx = canvas.getContext('2d');
  var interval = 10;

  var touchstart_mouseX = -1;
  var touchstart_mouseY = -1;
  var touchstartTime = 0;
  var touchTime = 1000;

//  var elevator;

  // ----------------------------------------------------------------
  // mouse or touch

  // mouse
  canvas.onmousemove=function(e){
    adjustLocation(e);
    return false;
  }

  canvas.onmouseup=function(e){
    adjustLocation(e);
    find_elevator_and_floor();
    return false;
  }

  // touch
  canvas.ontouchstart=function(){
    e=event.touches[0];    // first touch only
    adjustLocation(e);
    event.preventDefault();

    if (touchstart_mouseX == mouseX && touchstart_mouseY == mouseY) return false;
    touchstart_mouseX = mouseX;
    touchstart_mouseY = mouseY;
    touchstartTime = performance.now();
    touchHoldElevator = find_elevator_and_floor();

    return false;
  }

  canvas.ontouchmove=function(e){
    e=event.touches[0];    // first touch only
    adjustLocation(e);
    event.preventDefault();
    return false;
  }

  canvas.ontouchend=function(e){
    touchTime = performance.now() - touchstartTime;
    e=event.changedTouches[0];    // first touch only
    adjustLocation(e);

    if (Math.abs(touchHoldElevator - getElevator(mouseX)) < 2){
       mouseX = touchstart_mouseX;
    }
    find_elevator_and_floor();
    touchHoldElevator = -1;
    touchstart_mouseX = -1;
    touchstart_mouseY = -1;
    touchstartTime = 0;
    touchTime = 1000;

    return false;
  }

  function find_elevator_and_floor(){
    // find elevator, floor
    var elevator = getElevator(mouseX);
    if(elevator_ride_on[elevator] == false){
      elevator_target_floor[elevator] = getFloor(mouseY);

      var elevator_speed = Math.floor(Math.abs(mouseY - touchstart_mouseY) / ELEVATOR_HEIGHT * (800 / touchTime));
      if ( elevator_speed < 4) elevator_speed = 4;
      if ( elevator_speed > 8) elevator_speed = 8;
      if(elevator_vvy[elevator]<10){ elevator_vvy[elevator] += elevator_speed;}

      // touch animation
      touchX = elevator;
      touchY = elevator_target_floor[elevator];
      touchViewCounter = 255;
    }
    return elevator;
  }

  function adjustLocation(e){
    // adjust
    var rect = e.target.getBoundingClientRect();
    mouseX = e.clientX - rect.left;
    mouseY = e.clientY - rect.top;
  } // -------------------------


  function getFloorBottomY(floor) {
    return Math.floor(ELEVATOR_HEIGHT * (NUM_OF_FLOORS - floor + 1) + TOP_OFFSET);
  }

  function getFloorY(floor) {
    return Math.floor(ELEVATOR_HEIGHT * (NUM_OF_FLOORS - floor) + TOP_OFFSET);
  }

  function getFloor(Y) {
    var floor_num = NUM_OF_FLOORS - (Y - TOP_OFFSET) / ELEVATOR_HEIGHT + 1;

    if (floor_num < 1) floor_num = 1;
    if (floor_num > NUM_OF_FLOORS) floor_num = NUM_OF_FLOORS;

    return Math.floor(floor_num);
  }


  function getElevatorCenterX(e) {
    return Math.floor(e * ELEVATOR_WIDTH + ELEVATOR_WIDTH / 2 + LEFT_OFFSET);
  }

  function getElevator(X) {
    var elevator_num = (X - LEFT_OFFSET) / ELEVATOR_WIDTH;

    if (elevator_num < 0) elevator_num = 0;
    if (elevator_num > NUM_OF_ELEVATORS - 1) elevator_num = NUM_OF_ELEVATORS - 1;

    return Math.floor(elevator_num);
  }

  // Count Max for next person in. (Small = Busy)
  function countMaxForNextPersonIn(){
    var count = 80;
    switch (time_hour) {
      case 6:
        count = 600;
        break;
      case 7:
        count = 200;
        break;
      case 8:
        count = 40;
        break;
      case 9:
        count = 60;
        break;
      case 12:
        count = 20;
        break;
      case 17:
        count = 40;
        break;
      case 18:
        count = 30;
        break;
      case 19:
        count = 60;
        break;
      case 20:
        count = 70;
        break;
    }
    return count;
  }

  function person_fieldin_1stfloor_percentage(){
    var percentage = 80;
    switch (time_hour) {
      case 6:
        percentage = 90;
        break;
      case 7:
        percentage = 90;
        break;
      case 8:
        percentage = 60;
        break;
      case 9:
        percentage = 50;
        break;
    }
    return percentage;
  }

  // DRAW
  function draw(){
    ctx.clearRect(0, 0, WIDTH, HEIGHT);
    ctx.textAlign = "center";
    ctx.font = DEFAULT_FONT;

    // Draw Elevator Frame
    ctx.strokeStyle = "#eaa";
    for(var i = 0; i < NUM_OF_ELEVATORS; i++){
      ctx.beginPath();
      ctx.rect(ELEVATOR_WIDTH * i       + LEFT_OFFSET, TOP_OFFSET,
               ELEVATOR_WIDTH, FIELD_HEIGHT);
      ctx.stroke();
    }

    for(var i = 0; i < NUM_OF_FLOORS; i++){
      ctx.beginPath();
      ctx.moveTo(LEFT_OFFSET,               getFloorY(i));
      ctx.lineTo(LEFT_OFFSET + FIELD_WIDTH, getFloorY(i));
      ctx.stroke();
    }

    // Draw touch
    if(touchViewCounter > 0){
      var gb_color = 255 - touchViewCounter;
      ctx.fillStyle = 'rgb(255, ' + gb_color + ', ' + gb_color + ')';
      ctx.beginPath();
      ctx.fillRect(ELEVATOR_WIDTH * touchX       + LEFT_OFFSET , getFloorY(touchY) ,
                   ELEVATOR_WIDTH, ELEVATOR_HEIGHT);
      ctx.stroke();
      touchViewCounter-=15;
    }
    ctx.fillStyle = 'rgb(0,0,0)';

    // Draw Elevators
    for(var i = 0; i < NUM_OF_ELEVATORS; i++){
      if(elevator_ride_on[i] == true) {
        ctx.strokeStyle = "#f00";
      }else{
        ctx.strokeStyle = "#33f";
      }
      ctx.beginPath();
      ctx.rect(ELEVATOR_WIDTH * i       + LEFT_OFFSET, elevator_y[i],
               ELEVATOR_WIDTH,                         ELEVATOR_HEIGHT);
      ctx.stroke();

      // draw arrow
      if(elevator_y[i] != getFloorY(elevator_target_floor[i])) {
        var diff = Math.abs(elevator_y[i] - getFloorY(elevator_target_floor[i]));   // max field_height to 0
        var arrow_color = 255 - diff;
        var arrow_height;
        if( elevator_y[i] > getFloorY(elevator_target_floor[i]) ) arrow_height = 1; else arrow_height = -1;

        ctx.strokeStyle = 'rgb(' + 240 + ', ' + arrow_color + ', ' + arrow_color + ')';
        ctx.beginPath();

        var arrow_x = ELEVATOR_WIDTH * i + ELEVATOR_WIDTH / 2 + LEFT_OFFSET;
        var arrow_y_from = elevator_y[i] + ELEVATOR_HEIGHT / 2                        - arrow_height * ELEVATOR_HEIGHT * (FIELD_HEIGHT - diff) / FIELD_HEIGHT;
        var arrow_y_to = getFloorY(elevator_target_floor[i]) + ELEVATOR_HEIGHT / 2    + arrow_height * ELEVATOR_HEIGHT * diff / FIELD_HEIGHT / 2;

        if( arrow_height == -1 && arrow_y_from > arrow_y_to ) arrow_y_from = arrow_y_to;		// over
        if( arrow_height == 1 && arrow_y_from < arrow_y_to ) arrow_y_from = arrow_y_to;

        ctx.moveTo(arrow_x, arrow_y_from                           );	// From
        ctx.lineTo(arrow_x, arrow_y_to                             ); // To

        ctx.lineTo(arrow_x + ELEVATOR_WIDTH / 4, arrow_y_to        + arrow_height * ELEVATOR_HEIGHT / 4);     // To right arrow

        ctx.moveTo(arrow_x, arrow_y_to                             );    // To
        ctx.lineTo(arrow_x - ELEVATOR_WIDTH / 4, arrow_y_to        + arrow_height * ELEVATOR_HEIGHT / 4);     // To Left arrow

        ctx.stroke();
      }
    }

    // Draw Persons
    for(var i = 0; i < NUM_OF_PEOPLE; i++){

     if(person_in_field[i] == true){
      var person_y;
      if( person_ride_on[i] == false){
        person_y = getFloorBottomY(person_current_floor[i]);
      }else{
        person_y = elevator_y[person_ride_on_elevator[i]] + ELEVATOR_HEIGHT;		// ride on, get elevator y
      }

      var size = person_size[i];;
      ctx.beginPath();
      ctx.strokeStyle = 'rgb(' + person_angry_gauge[i] + ', 0, 0)';
      ctx.arc(person_x[i], person_y + person_offset * size, 3 * size, 0, Math.PI*2, true);
      ctx.moveTo(person_x[i] - 5 * size, person_y + (6 + person_offset) * size);
      ctx.lineTo(person_x[i] + 5 * size, person_y + (6 + person_offset) * size);

      ctx.moveTo(person_x[i], person_y + (3 + person_offset) * size);
      ctx.lineTo(person_x[i], person_y + (8 + person_offset) * size);

      ctx.moveTo(person_x[i],            person_y + (8 + person_offset) * size);
      ctx.lineTo(person_x[i] - 4 * size, person_y + (11 + person_offset) * size);

      ctx.moveTo(person_x[i],            person_y + (8 + person_offset) * size);
      ctx.lineTo(person_x[i] + 4 * size, person_y + (11 + person_offset) * size);

      ctx.stroke();

      ctx.fillText(person_target_floor[i], person_x[i] - 8, person_y - 4 + person_offset);

      // draw angry line
      if(person_angry_gauge[i] > 255){
        var building_value_height = FIELD_HEIGHT * building_value / BUILDING_VALUE_MAX;
        ctx.beginPath();
        ctx.strokeStyle = "#eaa";
        ctx.moveTo(person_x[i],     person_y + 8 + person_offset);
        ctx.lineTo(LEFT_OFFSET + FIELD_WIDTH + 2, TOP_OFFSET + FIELD_HEIGHT - building_value_height);
        ctx.stroke();
      }
     }
    }

    // Draw Arrived "OK"
    for(var i = 0; i < NUM_OF_ARRIVED; i++)
    {
      if(arrived_counter[i] > 0){
        ctx.fillStyle = 'rgba(0, 180, 0, ' + arrived_counter[i] / ARRIVED_COUNTER_MAX + ')';
        ctx.font = 12 + 10 * ((ARRIVED_COUNTER_MAX - arrived_counter[i]) / ARRIVED_COUNTER_MAX) + "pt 'Times New Roman'";
        ctx.fillText("OK", arrived_x[i], arrived_y[i] + ELEVATOR_HEIGHT / 2 * (arrived_counter[i] / ARRIVED_COUNTER_MAX));
        arrived_counter[i]--;
      }
    }
    ctx.font = DEFAULT_FONT;

    // Draw Building Value
    ctx.fillStyle = "#2EA2B0";
    ctx.beginPath();
    var building_value_height = FIELD_HEIGHT * building_value / BUILDING_VALUE_MAX;
    ctx.fillRect(LEFT_OFFSET + FIELD_WIDTH + 2, TOP_OFFSET + FIELD_HEIGHT - building_value_height,
             BUILDING_VALUE_WIDTH - 4, building_value_height);
    ctx.stroke();

    if(game_over == true){
        ctx.fillStyle = "#E9967A";
        ctx.font = 40 + "pt 'Times New Roman'";;
        ctx.fillText("Game Over", WIDTH / 2, HEIGHT / 2);
       return;
    }

    // Draw Time
    ctx.fillStyle = 'rgba(0, 0, 0)';
    var time_size = 1;  // 0-1
    var time_hold = 5;
    if(time_minute == 0){
       time_size = 1 - (time_sec_max - time_sec) / time_sec_max;
    }else if(time_minute < time_hold){
       time_size = 1;
    }else if(time_minute < 12){
       var ten_minute_max = (12 - time_hold) * time_sec_max;
       time_size = (ten_minute_max - (time_minute - (time_hold - 1)) * time_sec_max + (time_sec_max - time_sec)) / ten_minute_max;
    }else{
       time_size = 0;
    }
    ctx.font = 10 + 10 * time_size + "pt 'Times New Roman'";
    var text_minutes;
    if(time_minute < 10){
      text_minutes = "0" + time_minute;
    }else{
      text_minutes = time_minute;
    }
    if(time_hour < 12){
      ctx.fillText("AM " + time_hour + ":" + text_minutes, LEFT_OFFSET + FIELD_WIDTH / 2, TOP_OFFSET + (TOP_OFFSET + FIELD_HEIGHT / 4) * time_size);
    }else{
      ctx.fillText("PM " + (time_hour - 12)  + ":" + text_minutes, LEFT_OFFSET + FIELD_WIDTH / 2, TOP_OFFSET + (TOP_OFFSET + FIELD_HEIGHT / 4) * time_size);
    }
    ctx.font = DEFAULT_FONT;

    // move elevator
    for(var i = 0; i < NUM_OF_ELEVATORS; i++){
      if(elevator_y[i] > getFloorY(elevator_target_floor[i])) {elevator_y[i] -= elevator_vy[i];};
      if(elevator_y[i] < getFloorY(elevator_target_floor[i])) {elevator_y[i] += elevator_vy[i];};

      if(elevator_vvy[i] > 0) {elevator_vvy[i]--;}
      if(elevator_vvy[i] < 0) {elevator_vvy[i] = 0;}
      elevator_vy[i] = elevator_vy[i] + elevator_vvy[i];
      if(elevator_vy[i] > 1)  {elevator_vy[i]--;}
      if(elevator_vy[i] <= 0) {elevator_vy[i] = 1;}
    }

    // move persons
    for(var i = 0; i < NUM_OF_PEOPLE; i++){
     if(person_in_field[i] == true){
      // algorithm

      // find near elevator
      var min_distance = FIELD_WIDTH;
      for(var e = 0; e < NUM_OF_ELEVATORS; e++){
        if(elevator_target_floor[e] == person_current_floor[i]) {
          var distance = Math.abs(person_x[i] - getElevatorCenterX(e));
          if (min_distance > distance){
            person_near_elevator_num[i] = e;
            min_distance = distance;
          }
        }
      }

      if( person_ride_on[i] == false){
        if( person_x[i] > getElevatorCenterX(person_near_elevator_num[i]) ) person_x[i]-=0.5;
        if( person_x[i] < getElevatorCenterX(person_near_elevator_num[i]) ) person_x[i]+=0.5;

        person_angry_gauge[i]++;	// add angry guage
        if(person_angry_gauge[i] > 255){
          building_value--;
          if(building_value < 0) game_over = true;		// GAME OVER
        }
      }
     }
    }

    // ride on
    for(var i = 0; i < NUM_OF_PEOPLE; i++){
      for(var e = 0; e < NUM_OF_ELEVATORS; e++){
//        if( person_x[i] == getElevatorCenterX(e)){   					// x axis is e elevator
       if(person_in_field[i] == true){
        if( getElevator(person_x[i]) == e){
          if( person_ride_on[i] == false && elevator_y[e] == getFloorY(person_current_floor[i])){		// elevator y is person current floor Y and not ride on
           if( touchHoldElevator != e ){  // not touch hold elevator
            person_ride_on[i] = true;
            person_ride_on_elevator[i] = e;
            if(elevator_ride_on[e] == false){
              elevator_ride_on[e] = true;
              elevator_target_floor[e] = person_target_floor[i];
            }
           }
          }
        }
       }
      }
    }

    // arrived
    for(var i = 0; i < NUM_OF_PEOPLE; i++){
      if(person_in_field[i] == true && person_ride_on[i] == true && elevator_ride_on[person_ride_on_elevator[i]] == true){		// person rides on elevator

        if( elevator_y[person_ride_on_elevator[i]] == getFloorY(person_target_floor[i])){	// arrived!
            for(var j = 0; j < NUM_OF_ARRIVED; j++){	// for drawing arrived!
              if(arrived_counter[j] == 0){
                arrived_x[j] = person_x[i];
                arrived_y[j] = getFloorBottomY(person_target_floor[i]) - ELEVATOR_HEIGHT / 2 + person_offset;
                arrived_counter[j] = ARRIVED_COUNTER_MAX;
                building_value += 100;
                if(building_value > BUILDING_VALUE_MAX) building_value = BUILDING_VALUE_MAX;
                break;
              }
            }

            person_in_field[i] = false;	// out of game
            person_ride_on[i] = false;
        }
      }
    }

    // no one in the elevator check
    for(var e = 0; e < NUM_OF_ELEVATORS; e++){
      if(elevator_ride_on[e] == true){
        var no_person_in_the_elevator = true;
        for(var i = 0; i < NUM_OF_PEOPLE; i++){
          if(person_in_field[i] == true && e == person_ride_on_elevator[i]){
            no_person_in_the_elevator = false;

            // elevator is on the target floor and this person is not on the target floor. update elevator target floow
            if(elevator_y[e] == getFloorY(elevator_target_floor[e]) &&
              elevator_target_floor[e] != person_target_floor[i]){
              elevator_target_floor[e] = person_target_floor[i];
            }
            break;
          }
        }
        if(no_person_in_the_elevator == true){
           elevator_ride_on[e] = false;
        }
      }
    }

    // Time count
    time_sec++;
    if(time_sec > time_sec_max - 1){
      time_minute++;
      time_sec = 0;
    }
    if(time_minute > time_minute_max - 1){
      time_hour++;
      time_minute = 0;
    }
    if(time_hour > 23){
      time_hour = 0;
    }

    ctx.fillStyle = "#000";

    // Draw target floor
    for(var i = 0; i < NUM_OF_ELEVATORS; i++)
    {
      ctx.fillText(elevator_target_floor[i], ELEVATOR_WIDTH * i + 20, elevator_y[i] + 20);
    }

    // debug text
//    ctx.fillText(elevator + "," + counter + "," + mouseX + "," + mouseY, 10, 10);

    // person field in
    if( counter == 0){
      for(var i = 0; i < NUM_OF_PEOPLE; i++)
      {
        if( person_in_field[i] == false ){
          if(Math.random() * 10 > 5){
            person_x[i] = 0;
          }else{
            person_x[i] = FIELD_WIDTH;
          }
          if(person_fieldin_1stfloor_percentage() > Math.floor(Math.random() * 100)){
            person_current_floor[i] = 1;
          }else{
            person_current_floor[i] = Math.floor(Math.random() * NUM_OF_FLOORS + 1);
          }
          person_target_floor[i] = Math.floor(Math.random() * NUM_OF_FLOORS + 1);
          while(person_current_floor[i] == person_target_floor[i]){
            person_target_floor[i] = Math.floor(Math.random() * NUM_OF_FLOORS + 1);
          }
          person_in_field[i] = true;
          person_ride_on[i] = false;
          person_ride_on_elevator[i] = -1;
          person_near_elevator_num[i] = Math.floor(Math.random() * NUM_OF_ELEVATORS);
          person_angry_gauge[i] = 0;
          person_size[i] = Math.floor(Math.random() * 10);
          break;
        }
      }
    }
    counter++;
    if(counter > countMaxForNextPersonIn()) counter = 0;
  }

  var move = function() {
    draw();

    clearTimeout(timer);
    timer = setTimeout(move, interval);
  };


// play bgm
  var AUDIO_LIST = {
    "music00": new Audio("./Ant_Work.mp3")
  };
  for(var i in AUDIO_LIST){
//	AUDIO_LIST[i].load();
  }
//  AUDIO_LIST["music00"].play();

  move();
};
</script>
</CENTER></body>
</html>
