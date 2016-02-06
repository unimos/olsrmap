

<?php
  //The location where the OLSR daemon dumps the latlon file
  $latlonfile="/var/run/latlon.js";

  //The community where this script is installed.
  //WARNING: The HeyWhatsThat terrain profile service is provided freely by its author, Michael Kosowsky, under the
  //condition that whoever wants to use it, contacts him beforehand. His written permission is necessary to use the
  //profile tool.
  //Please do not enable the profile tool if you don't have written permission from Michael Kosowsky. Thank you
  //This will signal the origin of the requests to HeyWhatsThat:
  $srccommunity="";
?>

<!DOCTYPE html>
<html>
  <head>
    <title>OLSR nodes map</title>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
    <style type="text/css">
      html, body, #map {
        height: 100%;
        width: 100%;
        margin: 0;
        padding: 0;
      }
    </style>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?sensor=false&libraries=geometry">
    </script>
    <script type="text/javascript" src="WorldMagneticModel.js">
    </script>
    <script type="text/javascript">
      var map;
      var bounds; //Automagically defines the best bounds for the map

      var point = new Object();//"Associative Array" with all data from all nodes
      var alias = new Object();//"Associative Array" with the main ip of each alias

      // Some global vars to hold common Objects used multiple times
      var greenpoint = {
          path: google.maps.SymbolPath.CIRCLE,
          fillColor: "#7ac142",
          fillOpacity: 1,
          scale: 5,
          strokeWeight: 0
      };
      var infowindow;

      // Link animation related variables
      var animate = true;
      var animationMovingIcon = { path: 'M 0, -1 0, 2', strokeOpacity: 1, strokeColor: "#E4FF00", scale: 3 };
      var animationIntervalID = null;
      var animatedlines = new Array();

      // Distances and topology metrics
      var topographer = null;

      var coordinateMarker = null;



      /*
       * Topography analysis tools
       */

      function updateTopographer(event) {
        if (null == topographer.origin) {
          topographer.updateCoords(event.latLng, null);

          topographer.originDragger = google.maps.event.addListener(topographer.origin, "dragend", function() {
            topographer.updateCoords(topographer.origin.getPosition(), null);
          });
        } else {
          topographer.updateCoords(null, event.latLng);

          topographer.destinationDragger = google.maps.event.addListener(topographer.destination, "dragend", function() {
            topographer.updateCoords(null, topographer.destination.getPosition());
          });
        }
      }

      /*
       * Gets the source of a profile image, given the coordinates of two locations, frequency to use, curvature and a source.
       * TODO: This is currently a bit fragile, as no validation is done on the inputs.
       * TODO: Allow other fields to be parametrized.
       */
      function getProfileImageSrc (pt0lat, pt0lon, pt1lat, pt1lon, freq, curvature, src) {
        return "http://www.heywhatsthat.com/bin/profile-0904.cgi?" +
               "pt0=" + pt0lat + "," + pt0lon + ",ff0000,,ff0000&" +
               "pt1=" + pt1lat + "," + pt1lon + ",,,ff0000&" +
               "axes=1&metric=1&width=350&freq=" + freq + "&curvature=" + curvature + "<?php if (!empty($srccommunity)) echo "&src=$srccommunity"?>";
      }

      function Topographer () {
      /*
       {
         google.maps.Marker origin,
         google.maps.Marker destination,
         google.maps.event.Listener originDragger,
         google.maps.event.Listener destinationDragger,
         google.maps.event.Listener clickListener,
         google.maps.Polyline line,
         hasLine(),
         updateInfoWindow(),
         updateLine()
       }
       */
        this.clickListener = google.maps.event.addListener(map, "click", updateTopographer);
        this.line = null;
        this.hasLine = function () { return this.line != null; };

        this.updateCoords = function(originLatLng, destinationLatLng) {
          if (null != originLatLng) {
            if (null == this.origin) {
              this.origin = new google.maps.Marker({
                position: originLatLng,
                title: "Starting point",
                draggable: true,
                map: map
              });
            } else {
              this.origin.setPosition(originLatLng);
            }
          }
          if (null != destinationLatLng) {
            if (null == this.destination) {
              this.destination = new google.maps.Marker({
                position: destinationLatLng,
                title: "Ending point",
                draggable: true,
                map: map
              });
            } else {
              this.destination.setPosition(destinationLatLng);
            }

          }

          if (null == this.origin) { //TODO: get rid of this
            alert("Congratulations! You found a bug on the map! :-)\nPlease go to http://github.com/Pitxyoki/olsrmap/issues and say that you saw this message.");
            return;
          }

          if (null != this.origin && null != this.destination) {
            if (null == this.line) {
              this.line = new google.maps.Polyline({
                path: [
                        this.origin.getPosition(),
                        this.destination.getPosition()
                      ],
                strokeColor: "#FF0000",
                strokeOpacity: 1,
                clickable: false,
                zIndex: 5,
                map: map
              });
            } else { //line != null
              this.line.setPath([this.origin.getPosition(), this.destination.getPosition()]);
            }
            this.updateInfoWindow();
          }
        };

        this.updateInfoWindow = function () {
          if (!this.hasLine()) {
            infowindow.close();
            infowindow = null;
          } else {
            if (null == infowindow) {
              infowindow = new google.maps.InfoWindow();
            }
            var distanceMeters = Math.round(google.maps.geometry.spherical.computeDistanceBetween(this.origin.getPosition(), this.destination.getPosition())*100)/100;
            var trueNorthHeading = google.maps.geometry.spherical.computeHeading(this.origin.getPosition(), this.destination.getPosition());

            //This is the heading relative to True North, from one point to the other, given by Google
            //var headingTrueNorthCompass = Math.round(((headingTrueNorth + 2.8 + 360)%360) * 100)/100;

            //But when we're in the field, we use magnetic compasses
            var now = new Date();
            var yearStart    = new Date(now.getFullYear(), 0, 1);
            var yearLength   = new Date(now.getFullYear()+1, 0, 1) - yearStart;
            var nowYearFloat = now.getFullYear() + Math.round(((now - yearStart) / yearLength) * 100) / 100;

            var magneticDeclination = (new WorldMagneticModel()).declination(0.0, this.origin.getPosition().lat(), this.origin.getPosition().lng(), nowYearFloat);
            var headingMagneticCompass = Math.round(((trueNorthHeading - magneticDeclination + 360)%360) * 100) / 100;

            infowindow.setContent("<div style=\"font-size: 10pt; font-family: 'sans-serif'\">" +
                                    "<b>Distance:</b> " + distanceMeters + " m<br/>" +
                                    "<b>Magnetic heading at start:</b> " + headingMagneticCompass + "&deg;<br/><br/>" +

<?php if (!empty($srccommunity)) {
  echo <<<EOF
                                    "<b>Profile:</b><br/>" +
//                                    "<img src=\"http://profile.heywhatsthat.com/bin/profile.cgi?pt0=" + this.origin.getPosition().lat() + "," + this.origin.getPosition().lng() + "&pt1=" + this.destination.getPosition().lat() + "," + this.destination.getPosition().lng() + "&axes=1&metric=1&curvature=1&width=350\" />" +
                                    "<img id=\"profileImg\" src=" + getProfileImageSrc(this.origin.getPosition().lat(),      this.origin.getPosition().lng(),
                                                                                       this.destination.getPosition().lat(), this.destination.getPosition().lng(),
                                                                                       "2400", "0") + "/><br/>" +
                                    "<form onclick=\'" +
                                              "if (document.getElementById(\"roundearth\").checked) { curvature=\"1\"; } else { curvature=\"0\"; }; " +
                                              "if (document.getElementById(\"freqswitchbg\").checked) { freq=\"2400\"; } else { freq=\"5200\"; };" +
                                              "document.getElementById(\"profileImg\").src=getProfileImageSrc(" +
                                                                                      this.origin.getPosition().lat()      + ", " + this.origin.getPosition().lng()      + ", " +
                                                                                      this.destination.getPosition().lat() + ", " + this.destination.getPosition().lng() + ", " +
                                                                                      "freq, curvature)" +
                                      "\'>" +
                                      "<input type=\"checkbox\" id=\"roundearth\">Round Earth</input><br/>" +
                                      "<input type=\"radio\"    id=\"freqswitchbg\" name=\"freqswitch\" value=\"bg\" checked>802.11bg (2.4GHz)</input>" +
                                      "<input type=\"radio\"    id=\"freqswitcha\"  name=\"freqswitch\" value=\"a\">802.11a (5GHz)</input>" +
                                    "</form>" +

                                    "<div style=\"font-size:10px; text-align: right\">Profile image courtesy of <a href=\"http://www.heywhatsthat.com/faq.html\" target=\"_blank\">HeyWhatsThat</a><br/>" +
                                    "&copy;2013 Michael Kosowsky. All rights reserved. Used with permission.<br/></div>" +
EOF;
} ?>
                                  "</div>");
            infowindow.open(map, this.destination);
          }
        };

        this.kill = function () {
          if (null != this.origin)             this.origin.setMap(null);
          if (null != this.destination)        this.destination.setMap(null);
          if (null != this.originDragger)      google.maps.event.removeListener(this.originDragger);
          if (null != this.destinationDragger) google.maps.event.removeListener(this.destinationDragger);
          if (null != this.line)               this.line.setMap(null);
          google.maps.event.removeListener(this.clickListener);
          //infowindow.close(); //NO, because the user might have opened it on a node


          this.origin = null;
          this.destination = null;
          this.originDragger = null;
          this.destinationDragger = null;
          this.line = null;
          this.clickListener = null;
          //infowindow = null; //NO, see above
        };

      }

      /*
       * Coordinate determination tool
       */
      function updateCoordinateMarker(event) {
        if (null == coordinateMarker.marker) {
          coordinateMarker.marker = new google.maps.Marker({
            position: event.latLng,
            title: "Place coordinates",
            draggable: true,
            map: map
          });

          coordinateMarker.markerDragger = google.maps.event.addListener(coordinateMarker.marker, "dragend", function () {
            coordinateMarker.updateCoords();
            });
        } else {
          coordinateMarker.marker.setPosition(event.latLng);
        }
        coordinateMarker.updateCoords();
      }

      function CoordinateMarker() {
        //google.maps.Marker this.marker
        //google.maps.event.Listener clickListener
        //google.maps.event.Listener markerDragger
        //updateCoords()

        this.clickListener = google.maps.event.addListener(map, "click", updateCoordinateMarker);

        this.updateCoords = function () {
          if (null == infowindow) {
            infowindow = new google.maps.InfoWindow();
          }
          infowindow.setContent("<div style=\"font-size: 10pt; font-family: 'sans-serif'\">" +
                                    "<b>Coordinates:</b> " + Math.round(this.marker.getPosition().lat()*100000)/100000 +
                                                             ", " +
                                                             Math.round(this.marker.getPosition().lng()*100000)/100000 +
                                " </div>");
          infowindow.open(map, this.marker);
        }

        this.kill = function() {
          if (null != this.marker) this.marker.setMap(null);
          if (null != this.markerDragger) google.maps.event.removeListener(this.markerDragger);
          google.maps.event.removeListener(this.clickListener);

          this.marker = null;
          this.markerDragger = null;
          this.clickListener = null;
        }
      }

      function buildControls (controlsDiv) {
        controlsDiv.style.margin = '5px';

        var rulerUI = document.createElement('div');
        rulerUI.style.backgroundColor = 'white';
        rulerUI.style.borderStyle = 'solid';
        rulerUI.style.borderWidth = '1px';
        rulerUI.style.borderColor = '#717B87';
        rulerUI.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.4)';
        rulerUI.style.minWidth = '34px';
        rulerUI.style.cursor = 'pointer';
        rulerUI.style.textAlign = 'center';
        rulerUI.title = 'Measure distances';

        var rulerImg = document.createElement('div');
        rulerImg.style.width= '100%';
        rulerImg.style.height = '17px';
        rulerImg.style.backgroundImage = "url('images/ruler.png')";
        rulerImg.style.backgroundPosition = 'center center';
        rulerImg.style.backgroundRepeat = 'no-repeat';
        rulerUI.appendChild(rulerImg);

        var clickedButtonEffect = 'inset 0 1px 4px rgba(0, 0, 0, 0.4)';
        var unclickedButtonEffect = '0 2px 4px rgba(0, 0, 0, 0.4)';

        google.maps.event.addDomListener(rulerUI, 'click', function() {
          if (null == topographer) {
            if (null != coordinateMarker) {
              coordinateMarker.kill();
              coordinateMarker = null;
              compassUI.style.boxShadow = unclickedButtonEffect;
            }

            topographer = new Topographer();
            rulerUI.style.boxShadow = clickedButtonEffect;
          } else {
            topographer.kill();
            topographer = null;

            rulerUI.style.boxShadow = unclickedButtonEffect;
          }
        });


        var compassUI = document.createElement("div");
        compassUI.style.cssText = rulerUI.style.cssText;
        compassUI.style.minWidth = "16px";
        compassUI.title = "Determine coordinates";

        var compassImg = document.createElement("div");
        compassImg.style.cssText = rulerImg.style.cssText;
        compassImg.style.backgroundImage = "url('images/compass.png')";
        compassUI.appendChild(compassImg);

        google.maps.event.addDomListener(compassUI, "click", function (){
          if (null == coordinateMarker) {
            if (topographer != null) {
              topographer.kill();
              topographer = null;
              rulerUI.style.boxShadow = unclickedButtonEffect;
            }

            coordinateMarker = new CoordinateMarker()
            compassUI.style.boxShadow = clickedButtonEffect;
          } else {
            coordinateMarker.kill();
            coordinateMarker = null;

            compassUI.style.boxShadow = unclickedButtonEffect;
          }
        });


        var animationsUI = document.createElement("div");
        animationsUI.style.cssText = rulerUI.style.cssText;
        animationsUI.style.minWidth = "86px";
        animationsUI.title = "Disable Link Visibility Animations";

        var animationsText = document.createElement('div');
        animationsText.style.fontFamily = 'Arial,sans-serif';
        animationsText.style.fontSize = '13px';
        animationsText.style.padding = '1px 6px';
        animationsText.style.fontWeight = "bold";
        animationsText.innerHTML = 'Animations';
        animationsUI.appendChild(animationsText);

        google.maps.event.addDomListener(animationsUI, 'click', function() {
          animate = !animate;
          if (animate) {
            animationsText.style.fontWeight = "bold";
          } else {
            animationsText.style.fontWeight = "normal";
          }
        });



        controlsDiv.appendChild(rulerUI);
        controlsDiv.appendChild(compassUI);
        controlsDiv.appendChild(animationsUI);

      }


      /*
       * The popup that comes up when clicking a node
       */
      function getNeighboursTable(ip) {
        var result = "<table style='border: 0px; padding-left:2em; white-space: nowrap;'>";

        var currlink;
        for (var i = 0; i < point[ip].links.length; i++) {
          currlink = point[ip].links[i];
          result += "<tr>" +
                      "<td>" + point[currlink.toip].name + ":</td><td> LQ: " + currlink.lq + "</td><td> NLQ: " + currlink.nlq + "</td><td> ETX: " + currlink.etx + "</td>" +
                    "</tr>";
        }
        result += "</table>"
        return result;
      }

      function showNodeDetailWindow (ip) {
        if (null != infowindow) {
          infowindow.close();
          infowindow = null;
        }

        infowindow = new google.maps.InfoWindow({
          content: "<div style=\"font-size: 10pt; font-family: 'sans-serif'\"><b>" + point[ip].name + "</b><br/><br/>" +
                   "<b>Node IPs:</b> "+ ip +
                     (point[ip].aliases.length == 0 ? "" : ", " + point[ip].aliases) + "<br/>" +
                     (point[ip].links.length   == 0 ? "" : "<b>Neighbours:</b><br/>" + getNeighboursTable(ip)) +
                   "</div>"
        });

        infowindow.open(map, point[ip].marker);
      }


      /*
       * The animated lines that show up when hovering a node
       */
      function animateNodeLines (ip) {
        for (var i = 0; i < point[ip].links.length ; i++) {
          animatedlines.push(new google.maps.Polyline({
            path: point[ip].links[i].line.getPath(),
            strokeOpacity: 0,
            icons: [{
              icon: animationMovingIcon, //the same for all lines, defined globally
              offset: '0',
              repeat: '35px'
            }],
            zIndex: 10,
            map: map
          }));
        }

        var offset = 35;
        animationIntervalID = window.setInterval(function () {
          if (animate) {
            offset = (offset  + 32) % 35;
            for (var i = 0; i < animatedlines.length; i++) {
              var icons = animatedlines[i].get('icons');
              icons[0].offset = offset + "px";
              animatedlines[i].set("icons", icons);
            }
          }
        }, 100);
      }

      function stopAnimatingNodeLines() {
        while (animatedlines.length > 0) {
          animatedlines.shift().setMap(null);
        }

        animatedlines = new Array();
        window.clearInterval(animationIntervalID);
      }


      /*
       * All information we gather for each node
       */
      function OLSRNode (mainip, name) {
        this.name      = name;
        this.ip        = mainip;
        this.aliases   = new Array();
        this.links     = new Array(); //[{ toip, lq, nlq, etx, line<google.maps.Polyline> }]
        this.hasCoords = false;
        //set by setCoords:
        //this.lat
        //this.lon
        //this.marker

        this.setCoords = function(setLat, setLon) {
          this.lat = setLat;
          this.lon = setLon;

          this.marker = new google.maps.Marker({
              position: new google.maps.LatLng(setLat, setLon),
              icon: greenpoint,
              title: this.name,
              map: map
          });

          google.maps.event.addListener(this.marker, "click",     function() { showNodeDetailWindow(mainip); });
          google.maps.event.addListener(this.marker, "mouseover", function() { animateNodeLines(mainip);     });
          google.maps.event.addListener(this.marker, "mouseout",  function() { stopAnimatingNodeLines();   });

          bounds.extend(this.marker.getPosition());
          this.hasCoords = true;
        };

      }


      /*
       * Node dumping functions
       */
      function Mid (mainip, aliasip) {
        alias[aliasip] = mainip;
        if ( null == point[mainip]) {
          point[mainip] = new OLSRNode(mainip, null);//isn't there a better way?
        }
        point[mainip].aliases.push(aliasip);
      }


      function Node (mainip, lat, lon, ishna, hnaip, name) {
        if (null == point[mainip]) {
          point[mainip] = new OLSRNode(mainip, name);
        } else {
          point[mainip].name = name;
        }
        point[mainip].setCoords(lat, lon);
      }


      function Self (mainip, lat, lon, ishna, hnaip, name) {
        Node(mainip, lat, lon, ishna, hnaip, name);
      }


      function Link (fromip, toip, lq, nlq, etx) {
        if (null != alias[toip]) {
          toip = alias[toip];
        }
        if (null != alias[fromip]) {
          fromip = alias[fromip];
        }
        if (null != point[fromip] && null != point[toip] &&
            point[fromip].hasCoords && point[toip].hasCoords) {

          point[fromip].links.push({
            toip: toip,
            lq: lq,
            nlq: nlq,
            etx: etx,

            line: new google.maps.Polyline({
              path: [
                point[fromip].marker.getPosition(),
                point[toip].marker.getPosition()
              ],
              strokeColor: ( //TODO: this should be a function, referenced by the multiple places that need this
                          Math.round(google.maps.geometry.spherical.computeDistanceBetween(point[fromip].marker.getPosition(), point[toip].marker.getPosition())*100)/100
                           ) > 10000 ? "#808fff" : "#FF0000",

              strokeOpacity: 0.5,
              clickable: false,
              zIndex: 5,
              map: map
            })
          });


        } else {
          //TODO: Add debug code here?
        }
      }
      
      function PLink (fromip, toip, lq, nlq, etx, lata, lona, ishnaa, latb, lonb, ishnab) {
        Link(fromip, toip, lq, nlq, etx);
      }
      
      function initialize () {

        map = new google.maps.Map(document.getElementById("map"), { mapTypeId: google.maps.MapTypeId.ROADMAP });
        bounds = new google.maps.LatLngBounds();

        var controls = document.createElement("div");
        controls.index = -1;
        buildControls(controls);

        map.controls[google.maps.ControlPosition.TOP_RIGHT].push(controls);
        
        //The nodes dump goes here
        var INFINITE = 99.9;
        <?php
          if(file_exists($latlonfile)) {
            $file = fopen($latlonfile, "r");
            echo fread($file,500000);
          }
        ?>

        if ( ! bounds.isEmpty()) {
          map.fitBounds(bounds);
        } else {
          //Center the map between Nazaré and Ourém
          map.setCenter(new google.maps.LatLng(39.6183836, -8.8302612));
          map.setZoom(11);
        }
        <?php
          if (isset($_GET['debug'])) {
            echo "debug();";
          }
        ?>
      }


      function debug() {
        document.getElementById("map").style.height = "80%";
        document.getElementById("debug").style.display = "inline";
        var nodestable = document.getElementById("debugTable");
        var row;
        var cell;

        for (var i in point) {
          row  = document.createElement("tr");
          cell = document.createElement("td");
          cell.innerHTML = point[i].ip;
          row.appendChild(cell);

          cell = document.createElement("td");
          if (null == point[i].name) {
            cell.style.backgroundColor = "#FF0000";
            cell.style.color = "#000000";
            cell.innerHTML = "NO";
          } else {
            cell.innerHTML = point[i].name;
          }
          row.appendChild(cell);

          cell = document.createElement("td");
          if (! point[i].hasCoords) {
            cell.style.backgroundColor = "#FF0000";
            cell.style.color = "#000000";
            cell.innerHTML = "NO";
          } else {
            cell.innerHTML = point[i].lat + ", " + point[i].lon;
          }
          row.appendChild(cell);

          nodestable.appendChild(row);
        }
      }
    </script>
  </head>
  <body onLoad="initialize()">
    <div id="map" style="width: 100%; height: 100%"></div>
    <div id="debug" style="width: 100%; height: 10%; display: none;">
      <table id="debugTable">
        <tr><td>Node's Main IP</td><td>Name</td><td>Coordinates</td></tr>
      </table>
    </div>
  </body>
</html>
