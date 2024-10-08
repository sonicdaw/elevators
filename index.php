<?php
$num_of_floors = intval($_GET["floor"]);
$num_of_elevators = intval($_GET["elevator"]);
$screen = $_GET["screen"];
if($num_of_floors == 0) $num_of_floors = 4;
if($num_of_elevators == 0) $num_of_elevators = 4;
?><html>
<head>
<title>Elebeater</title>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
</head>
<body><CENTER>
<button id="play">Audio ON</button>
<a href="./index.php">4x4</a>
<a href="./index.php?floor=8&elevator=4">4x8</a>
<a href="./index.php?floor=16&elevator=10">10x16</a>
<a href="https://github.com/sonicdaw/elevators/blob/master/index.php" target="_blank">Code</a><br>
Readme(<a href="https://elebeater.net/Elebeater.pdf" target="_blank">JP</a>/<a href="https://github.com/sonicdaw/elevators" target="_blank">EN</a>)
<a href="https://entaflip.com/" target="_blank">Developer</a>
<?php if( strcmp($screen, "PCmode") != 0){ print '<a href="./index.php?screen=PCmode">PC Play</a>';} else {print '<a href="./">FitScreen</a>'; } ?>
<br><br>

<!--[if IE]><script type="text/javascript" src="excanvas.js"></script><![endif]-->
<canvas id="cvs" />
<script type="text/javascript">
window.onload = function() {

  var WIDTH, HEIGHT;
  var FIELD_WIDTH, FIELD_HEIGHT, ELEVATOR_WIDTH, ELEVATOR_HEIGHT;
  const LEFT_OFFSET = 10;
  const TOP_OFFSET = 10;
  const NUM_OF_PEOPLE = 1000;
  const DEFAULT_FONT = "bold 8pt 'Times New Roman'";

  var score = 0;
  var score_on_screen = 0;
  var time_hour = 6;
  var time_minute = 0;
  var time_sec = 0;
  var time_day = 0;
  const time_minute_max = 60;
  const time_sec_max = 50;
  var game_over = false;
  var game_over_touchlock = 0;

  // million dollar
  const BUILDING_VALUE_MAX = 10000;
  var building_value = BUILDING_VALUE_MAX;
  const BUILDING_VALUE_WIDTH = 20;

  var counter = 0;
  const NUM_OF_ELEVATORS = <?php echo $num_of_elevators ?>;
  const NUM_OF_FLOORS = <?php echo $num_of_floors ?>;

  var canvas = document.getElementById('cvs');
  if (!canvas.getContext) {
    return false;
  }

  var ctx = canvas.getContext('2d');

  fitCanvasSize();
  window.onresize = fitCanvasSize;

  var elevator_y =  new Array(NUM_OF_ELEVATORS);
  var elevator_vy =  new Array(NUM_OF_ELEVATORS);
  var elevator_vvy =  new Array(NUM_OF_ELEVATORS);
  var elevator_target_floor =  new Array(NUM_OF_ELEVATORS);
  var elevator_ride_on = new Array(NUM_OF_ELEVATORS);
  var elevator_combo = new Array(NUM_OF_ELEVATORS);

  var person_in_field = new Array(NUM_OF_PEOPLE);
  var person_active_in_field = new Array(NUM_OF_PEOPLE);
  var person_x =  new Array(NUM_OF_PEOPLE);
  var person_y =  new Array(NUM_OF_PEOPLE);
  var person_current_floor =  new Array(NUM_OF_PEOPLE);
  var person_target_floor =   new Array(NUM_OF_PEOPLE);
  var person_ride_on = new Array(NUM_OF_PEOPLE);
  var person_ride_on_elevator = new Array(NUM_OF_PEOPLE);
  var person_near_elevator_num = new Array(NUM_OF_PEOPLE);
  var person_angry_gauge = new Array(NUM_OF_PEOPLE);
  var person_offset = -11;
  var person_size = new Array(NUM_OF_PEOPLE);
  var person_moveout_vx = new Array(NUM_OF_PEOPLE);
  var person_move_counter = new Array(NUM_OF_PEOPLE);
  const PERSON_MOVE_COUNTER_MAX = 40;

  const NUM_OF_ARRIVED = 20;
  const ARRIVED_COUNTER_MAX = 200;
  var arrived_x = new Array(NUM_OF_ARRIVED);
  var arrived_y = new Array(NUM_OF_ARRIVED);
  var arrived_counter = new Array(NUM_OF_ARRIVED);
  var arrived_score = new Array(NUM_OF_ARRIVED);

  var mouseX, mouseY;
  var touchX = 0;
  var touchY = 0;
  var touchViewCounter = 0;
  var touchHoldElevator = -1;
  var timer;
  var mouse_click = false;

  var interval = 10;

  var touchstart_mouseX = -1;
  var touchstart_mouseY = -1;
  var touchstartTime = 0;
  var touchTime = 1000;

  var touchVisualizerX = -1;
  var touchVisualizerY = -1;
  var touchVisualizerCounter = 0;

  var bgm_1, bgm_2, bgm_alert;
  var se_elevator_arrived_high;
  var se_elevator_arrived_mid;
  var se_elevator_arrived_low;
  var sound_loaded = false;
  var playing_bgm = 0;

  const getFloorBottomY = floor => Math.floor(ELEVATOR_HEIGHT * (NUM_OF_FLOORS - floor + 1) + TOP_OFFSET);
  const getFloorY = floor => Math.floor(ELEVATOR_HEIGHT * (NUM_OF_FLOORS - floor) + TOP_OFFSET);
  const getFloor = y => Math.min(Math.max(Math.floor(NUM_OF_FLOORS - (y - TOP_OFFSET) / ELEVATOR_HEIGHT + 1), 1), NUM_OF_FLOORS);
  const getElevatorCenterX = e => Math.floor(e * ELEVATOR_WIDTH + ELEVATOR_WIDTH / 2 + LEFT_OFFSET);
  const getElevator = x => Math.min(Math.max(Math.floor((x - LEFT_OFFSET) / ELEVATOR_WIDTH), 0), NUM_OF_ELEVATORS - 1);
  const getElevatorOnPerson = (x, personWidth) => {
    for(let i = 0; i < NUM_OF_ELEVATORS; i++){
      if(x > ELEVATOR_WIDTH * i + LEFT_OFFSET                  + Math.abs(personWidth) &&
         x < ELEVATOR_WIDTH * i + LEFT_OFFSET + ELEVATOR_WIDTH - Math.abs(personWidth)) {
        return i;
      }
      if(x === Math.floor(ELEVATOR_WIDTH * i + LEFT_OFFSET + ELEVATOR_WIDTH / 2)){
        return i;
      }
    }
    return -1;
  };

  // Count Max for next person in. (Small = Busy)
  const countMaxForNextPersonIn = () => {
    let count = 80;
    switch (time_hour) {
      case 6:  count = 600; break;
      case 7:  count = 200; break;
      case 8:  count = 40;  break;
      case 9:  count = 60;  break;
      case 12: count = 20;  break;
      case 17: count = 40;  break;
      case 18: count = 30;  break;
      case 19: count = 60;  break;
      case 20: count = 70;  break;
    }
    return count;
  };

  const person_fieldin_1stfloor_percentage = () => {
    let percentage = 80;
    switch (time_hour) {
      case 6: case 7: percentage = 90; break;
      case 8: percentage = 60; break;
      case 9: percentage = 50; break;
    }
    return percentage;
  };

  init_game();

//  var elevator;
  function fitCanvasSize() {
    var w = document.documentElement.clientWidth;
    var h = document.documentElement.clientHeight - 120;
    if(w > 300) w = 300;
<?php if( strcmp($screen, "PCmode") == 0){ print "    if(h > 600) h = 600;"; } ?>
    canvas.width = w;
    canvas.height = h;

    WIDTH = w;
    HEIGHT = h;
    FIELD_WIDTH = WIDTH - BUILDING_VALUE_WIDTH - 20;
    FIELD_HEIGHT = HEIGHT - TOP_OFFSET * 2;
    ELEVATOR_WIDTH = FIELD_WIDTH / NUM_OF_ELEVATORS;
    ELEVATOR_HEIGHT = FIELD_HEIGHT / NUM_OF_FLOORS;
  }

  // ----------------------------------------------------------------
  // mouse or touch

  // mouse
  canvas.onmousedown=function(e){
    adjustLocation(e);
    touchHoldElevator = find_elevator_and_floor();
    mouse_click = true;
    return false;
  }

  canvas.onmousemove=function(e){
    if(mouse_click == true){
       adjustLocation(e);
    }else{
       adjustLocationWithoutTouchVisualizer(e);
    }
    return false;
  }

  canvas.onmouseup=function(e){
    adjustLocation(e);
    find_elevator_and_floor();
    touchHoldElevator = -1;
    mouse_click = false;
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

  function init_game(){
    score = 0;
    score_on_screen = 0;
    time_hour = 6;
    time_minute = 0;
    time_sec = 0;
    time_day = 0;

    game_over = false;
    game_over_touchlock = 0;
    building_value = BUILDING_VALUE_MAX;
    counter = 0;
    for(var i = 0; i < NUM_OF_ELEVATORS; i++)
    {
      elevator_target_floor[i] = 1;  // ground floor
      elevator_y[i] = getFloorY(elevator_target_floor[i]);
      elevator_vy[i] = 1;
      elevator_vvy[i] = 0;
      elevator_ride_on[i] = false;
      elevator_combo[i] = 1;
    }

    for(var i = 0; i < NUM_OF_PEOPLE; i++)
    {
      person_in_field[i] = false;
      person_active_in_field[i] = false;
      person_x[i] = -1;
      person_current_floor[i] = 4;	// gound floor
      person_target_floor[i] = 1;
      person_ride_on[i] = false;
      person_ride_on_elevator[i] = -1;
      person_near_elevator_num[i] = 0;
      person_angry_gauge[i] = 0;
      person_size[i] = 1;
      person_move_counter[i] = 0;
    }

    for(var i = 0; i < NUM_OF_ARRIVED; i++)
    {
      arrived_x[i] = 0;
      arrived_y[i] = 0;
      arrived_counter[i] = 0;
      arrived_score[i] = 0;
    }

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

    touchVisualizerX = mouseX;
    touchVisualizerY = mouseY;
    touchVisualizerCounter = 30;

    // Open window for X Post
    if(game_over == true && mouseY >= HEIGHT * 4 / 5 - 30 && mouseY <= HEIGHT * 4 / 5 + 50){
      touchHoldElevator = -1; // mouse up
      mouse_click = false;
      var tweet_url = generateTwitterUrl(score);
      window.open(tweet_url, '_blank');

      return;
    }

    if(game_over == true && game_over_touchlock == 0){
      init_game();
      game_over = false;
      bgm_stop();
    }
  } // -------------------------


  function adjustLocationWithoutTouchVisualizer(e){
    // adjust
    var rect = e.target.getBoundingClientRect();
    mouseX = e.clientX - rect.left;
    mouseY = e.clientY - rect.top;
  }

  // DRAW
  function draw(){
    ctx.clearRect(0, 0, WIDTH, HEIGHT);
    ctx.textAlign = "center";
    ctx.font = DEFAULT_FONT;
    ctx.lineWidth = 1;

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
    if(touchViewCounter > 0 && game_over == false){
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
        ctx.strokeStyle = "#aaa";
      }else{
        if(touchHoldElevator == i){
          ctx.strokeStyle = "#f00"; // touch hold
        }else{
          ctx.strokeStyle = "#55f"; // not touch hold
        }
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

      var size = person_size[i];;
      ctx.beginPath();
      if(person_active_in_field[i] == true){
        ctx.strokeStyle = 'rgb(' + person_angry_gauge[i] + ', 0, 0)';	// active color
      }else{
        ctx.strokeStyle = 'rgb(194,194,194)';	// arrived color
      }
      ctx.arc(person_x[i], person_y[i] + person_offset * size, 3 * size, 0, Math.PI*2, true);
      ctx.moveTo(person_x[i] - 5 * size, person_y[i] + (6 + person_offset) * size);
      ctx.lineTo(person_x[i] + 5 * size, person_y[i] + (6 + person_offset) * size);

      ctx.moveTo(person_x[i], person_y[i] + (3 + person_offset) * size);
      ctx.lineTo(person_x[i], person_y[i] + (8 + person_offset) * size);

      ctx.moveTo(person_x[i],            person_y[i] + (8 + person_offset) * size);
      ctx.lineTo(person_x[i] - 4 * size * (PERSON_MOVE_COUNTER_MAX - person_move_counter[i]) / PERSON_MOVE_COUNTER_MAX, person_y[i] + (11 + person_offset) * size);

      ctx.moveTo(person_x[i],            person_y[i] + (8 + person_offset) * size);
      ctx.lineTo(person_x[i] + 4 * size * (PERSON_MOVE_COUNTER_MAX - person_move_counter[i]) / PERSON_MOVE_COUNTER_MAX, person_y[i] + (11 + person_offset) * size);

      ctx.stroke();

      if(person_active_in_field[i] == true){
        ctx.fillStyle = 'rgb(0,0,0)';
      }else{
        ctx.fillStyle = 'rgb(194,194,194)';
      }
      ctx.fillText(person_target_floor[i], person_x[i] - 8, person_y[i] - 4 + person_offset);

      // draw angry line
      if(person_angry_gauge[i] > 255){
        var building_value_height = FIELD_HEIGHT * building_value / BUILDING_VALUE_MAX;
        ctx.beginPath();
        ctx.strokeStyle = "#eaa";
        ctx.moveTo(person_x[i],     person_y[i] + 8 + person_offset);
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
        ctx.font = 12 + 20 * ((ARRIVED_COUNTER_MAX - arrived_counter[i]) / ARRIVED_COUNTER_MAX) * arrived_score[i] / 3 + "pt 'Times New Roman'";
        ctx.fillText("+" + arrived_score[i] * 10, arrived_x[i], arrived_y[i] + ELEVATOR_HEIGHT / 2 * (arrived_counter[i] / ARRIVED_COUNTER_MAX));
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


    // Free Text Area
    ctx.beginPath();
    ctx.strokeStyle = "#eaa";
    ctx.font = "18pt 'Times New Roman'";
    ctx.fillText("Smile Job Adventure", LEFT_OFFSET + FIELD_WIDTH / 2, TOP_OFFSET + (TOP_OFFSET + FIELD_HEIGHT / 8));
    ctx.font = "17pt 'Times New Roman'";
    ctx.fillText("Isle Shinagawa", LEFT_OFFSET + FIELD_WIDTH / 2, TOP_OFFSET + (TOP_OFFSET + FIELD_HEIGHT / 8 * 1.5));
    ctx.stroke();


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
    ctx.font = `${10 + 10 * time_size}pt 'Times New Roman'`;
    const text_minutes = (time_minute < 10) ? `0${time_minute}` : time_minute;
    const time_text = (time_hour < 12) ? `AM ${time_hour}:${text_minutes}` : `PM ${time_hour - 12}:${text_minutes}`;
    ctx.fillText(time_text, LEFT_OFFSET + FIELD_WIDTH / 2, TOP_OFFSET + (TOP_OFFSET + FIELD_HEIGHT / 4) * time_size);
    ctx.font = DEFAULT_FONT;

    // Draw Score
    if(score > score_on_screen) score_on_screen++;
    ctx.textAlign = "left";
    ctx.font = "10pt 'Times New Roman'";
    ctx.fillText("SCORE: " + score_on_screen, LEFT_OFFSET, TOP_OFFSET);
    ctx.textAlign = "center";

    if(game_over_touchlock > 0) game_over_touchlock--;
    if(game_over == true){
        ctx.fillStyle = "#E9967A";
        ctx.font = 40 + "pt 'Times New Roman'";
        ctx.fillText("Game Over", WIDTH / 2, HEIGHT / 2);

        // Draw button to X Post
        ctx.fillStyle = "#000000";
        ctx.roundRect(WIDTH * 1 / 20, HEIGHT * 4 / 5 - 20, WIDTH * 18 / 20, 60, 30);
        ctx.fill();

        ctx.shadowColor = "rgba(0, 0, 0, 0.5)";
        ctx.shadowBlur = 5;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 5;
        ctx.fillStyle = "#000000";
        ctx.fill();

        ctx.strokeStyle = "#FFFFFF";
        ctx.lineWidth = 3;
        ctx.shadowColor = "transparent";
        ctx.stroke();

        ctx.fillStyle = "#FFFFFF";
        ctx.font = "bold 20pt 'Arial'";
        ctx.fillText("Post score to X", WIDTH / 2, HEIGHT * 4 / 5 + 20);

        if(game_over_touchlock == 0){
          ctx.fillStyle = "#E9967A";
          ctx.font = 30 + "pt 'Times New Roman'";
          ctx.fillText("Tap to replay", WIDTH / 2, HEIGHT * 1 / 3);
          if(bgm_alert!=null){
            bgm_alert.loop = false;
          }
        }
        score_on_screen = score;
       return;
    }

    // Draw touch visualizer
    ctx.lineWidth = 15;
    if(touchVisualizerX != -1 && touchVisualizerY != -1){
      ctx.beginPath();
      ctx.strokeStyle = 'rgba(255, 0, 0, 0.5)';
      ctx.arc(touchVisualizerX, touchVisualizerY, touchVisualizerCounter, 0, Math.PI*2, true);
      ctx.stroke();
      if(touchHoldElevator==-1){
        touchVisualizerCounter=touchVisualizerCounter-0.5;
      }
      if(touchVisualizerCounter<=0){
        touchVisualizerX = -1;
        touchVisualizerY = -1;
      }
    }
    ctx.lineWidth = 1;

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
     if(person_active_in_field[i] == true){

      if( person_ride_on[i] == false){
        person_y[i] = getFloorBottomY(person_current_floor[i]);
      }else{
        person_y[i] = elevator_y[person_ride_on_elevator[i]] + ELEVATOR_HEIGHT;		// ride on, get elevator y
      }

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
        if( person_x[i] > getElevatorCenterX(person_near_elevator_num[i]) ) {
          person_x[i]-=0.5;
          person_move_counter[i] = (person_move_counter[i] + 1) % PERSON_MOVE_COUNTER_MAX;
        }else if( person_x[i] < getElevatorCenterX(person_near_elevator_num[i]) ) {
          person_x[i]+=0.5;
          person_move_counter[i] = (person_move_counter[i] + 1) % PERSON_MOVE_COUNTER_MAX;
        }else{
          person_move_counter[i] = 0;
        }

        person_angry_gauge[i]++;	// add angry guage
        if(person_angry_gauge[i] > 255){
          building_value--;
          if(building_value < 0) {game_over = true; game_over_touchlock = 400;}		// GAME OVER
        }
      }else{
        person_move_counter[i] = 0;
      }
     }else{		// arrived (not active) and move to out of floor
      person_x[i] += person_moveout_vx[i];
      if( person_x[i] < 0 || person_x[i] > FIELD_WIDTH ) {
         person_in_field[i] = false;	// out of field
      }
     }
    }
   }

    // ride on
    for(var i = 0; i < NUM_OF_PEOPLE; i++){
      for(var e = 0; e < NUM_OF_ELEVATORS; e++){
//        if( person_x[i] == getElevatorCenterX(e)){   					// x axis is e elevator
       if(person_active_in_field[i] == true){
        if( getElevatorOnPerson(person_x[i], 5 * person_size[i]) == e){
          if( person_ride_on[i] == false && elevator_y[e] == getFloorY(person_current_floor[i])){		// elevator y is person current floor Y and not ride on
           if( touchHoldElevator != e ){  // not touch hold elevator
            person_ride_on[i] = true;
            person_angry_gauge[i] = 0;
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
      if(person_active_in_field[i] == true && person_ride_on[i] == true && elevator_ride_on[person_ride_on_elevator[i]] == true){		// person rides on elevator

        if( elevator_y[person_ride_on_elevator[i]] == getFloorY(person_target_floor[i])){	// arrived!
            for(var j = 0; j < NUM_OF_ARRIVED; j++){	// for drawing arrived!
              if(arrived_counter[j] == 0){
                arrived_x[j] = person_x[i];
                arrived_y[j] = getFloorBottomY(person_target_floor[i]) - ELEVATOR_HEIGHT / 2 + person_offset;
                arrived_counter[j] = ARRIVED_COUNTER_MAX;
                arrived_score[j] = elevator_combo[person_ride_on_elevator[i]];
                elevator_combo[person_ride_on_elevator[i]]++;
                building_value += 80 + arrived_score[j] * 20;
                if(building_value > BUILDING_VALUE_MAX) building_value = BUILDING_VALUE_MAX;
                score += arrived_score[j] * 10;
                if(sound_loaded){
                   if(arrived_score[j] < 3){
                     se_elevator_arrived_low.play();
                   }else if(arrived_score[j] < 6){
                     se_elevator_arrived_mid.play();
                   }else{
                     se_elevator_arrived_high.play();
                   }
                }
                break;
              }
            }

            person_active_in_field[i] = false;	// out of game
            person_ride_on[i] = false;
            person_moveout_vx[i] = Math.floor(Math.random() * 5) - 2;
            if(person_moveout_vx[i] == 0) person_moveout_vx[i] = 1;
            person_moveout_vx[i] = person_moveout_vx[i] * 2;
        }
      }
    }

    // no one in the elevator check
    for(var e = 0; e < NUM_OF_ELEVATORS; e++){
      if(elevator_ride_on[e] == true){
        var no_person_in_the_elevator = true;
        for(var i = 0; i < NUM_OF_PEOPLE; i++){
          if(person_active_in_field[i] == true && e == person_ride_on_elevator[i]){
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
           elevator_combo[e] = 1;
        }
      }
    }

    // switch BGM
    if(building_value < BUILDING_VALUE_MAX / 3) { // Alert BGM
      if(playing_bgm != 3){
        bgm_pause();
        if(bgm_alert!=null){
          bgm_alert.currentTime = 0;
          bgm_alert.loop = true;
          bgm_alert.play();
          playing_bgm = 3;
        }
      }
    }else{                                        // Game BGM
      if(time_day == 0){     
        if(playing_bgm !=1 ){                                         // BGM1 (First Day)
          bgm_pause();
          if(bgm_1!=null){
            bgm_1.play();
            playing_bgm = 1;
          }
        }
      }else{                                                          // BGM2 (Second Day or later)
        if(playing_bgm !=2 ){
          bgm_pause();
          if(bgm_2!=null){
            bgm_2.play();
            playing_bgm = 2;
          }
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
      time_day++;
      time_hour = 0;
    }

    ctx.fillStyle = "#000";

    // Draw target floor
    for(var i = 0; i < NUM_OF_ELEVATORS; i++){
      ctx.fillText(elevator_target_floor[i], ELEVATOR_WIDTH * i + 20, elevator_y[i] + 20);
    }

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
          person_y[i] = getFloorBottomY(person_current_floor[i]);
          person_target_floor[i] = Math.floor(Math.random() * NUM_OF_FLOORS + 1);
          while(person_current_floor[i] == person_target_floor[i]){
            person_target_floor[i] = Math.floor(Math.random() * NUM_OF_FLOORS + 1);
          }
          person_in_field[i] = true;
          person_active_in_field[i] = true;
          person_ride_on[i] = false;
          person_ride_on_elevator[i] = -1;
          person_near_elevator_num[i] = Math.floor(Math.random() * NUM_OF_ELEVATORS);
          person_angry_gauge[i] = 0;
          person_size[i] = NUM_OF_FLOORS < 9 ? Math.floor(Math.random() * 9 + 1) : Math.floor(Math.random() * 4 + 1);
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
  document.getElementById('play').addEventListener('click', function () {
    if(bgm_1==null){
      bgm_1 = new Audio('./music/Ant_Work.mp3');
      bgm_1.load();
      bgm_1.loop = true;
      bgm_1.play();
      playing_bgm = 1;
    }
    if(bgm_2==null){
      bgm_2 = new Audio('./music/Etude_Plus_Op10No1_MSumi.mp3');
      bgm_2.load();
      bgm_2.loop = true;
    }
    if(bgm_alert==null){
      bgm_alert = new Audio('./music/02012020.m4a');
      bgm_alert.load();
      bgm_alert.loop = true;
    }
    load_se();
  });

  function bgm_pause(){
    if(bgm_1) { bgm_1.pause(); }
    if(bgm_2) { bgm_2.pause(); }
    if(bgm_alert) {bgm_alert.pause(); }
  }

  function bgm_stop(){
    if(bgm_1) { bgm_1.pause(); bgm_1.currentTime = 0;}
    if(bgm_2) { bgm_2.pause(); bgm_2.currentTime = 0;}
    if(bgm_alert) {bgm_alert.pause(); bgm_alert.currentTime = 0;}
  }

  function load_se(){
    if(!sound_loaded){
      se_elevator_arrived_high = new Audio('./sound/se_elevator_arrived_high.m4a');
      se_elevator_arrived_mid = new Audio('./sound/se_elevator_arrived_mid.m4a');
      se_elevator_arrived_low = new Audio('./sound/se_elevator_arrived_low.m4a');
      se_elevator_arrived_high.load();
      se_elevator_arrived_mid.load();
      se_elevator_arrived_low.load();
      sound_loaded = true;
    }
  }

  // Generate URL For X Post
  function generateTwitterUrl(score) {
    var playtime;
    const text_minutes = (time_minute < 10) ? `0${time_minute}` : time_minute;
    const time_text = (time_hour < 12) ? `AM ${time_hour}:${text_minutes}` : `PM ${time_hour - 12}:${text_minutes}`;
    if(time_day == 0 || time_day == 1){
      playtime = time_day + " day " + time_text;
    }else{
      playtime = time_day + " days " + time_text;
    }
    var building = "<?php echo $num_of_elevators ?> elevators <?php echo $num_of_floors ?> floors building";
    var text = "Elebeater\n\nScore: " + score + " (" + playtime + ")\n" + building + "\n\n" + "#Elebeater" + "\n" + "https://elebeater.net";
    var tweet_url = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(text);
    return tweet_url;
  }

  move();
};
</script>
</CENTER></body>
</html>
