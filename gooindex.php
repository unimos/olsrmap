

<?php
  //The OLSR daemon must output the latlon file to this location
  $latlonfile="/var/run/latlon.js";
?>

<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
    <style type="text/css">
      html, body, #map {
        height: 100%;
        margin: 0;
        padding: 0;
      }
    </style>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?sensor=false&libraries=geometry">
    </script>
    <script type="text/javascript">
      var map;
      var bounds;
      var alias = new Array;
      var mainipaliases = new Array;
      var mainiplinks = new Array;
      var mainipnames = new Array;
      var mainiplines = new Array;
      var points = new Array;
      var unkpos = new Array;
      var lineid = 0;
      var greenpoint = {
          path: google.maps.SymbolPath.CIRCLE,
          fillColor: "#7ac142",
          fillOpacity: 1,
          scale: 5,
          strokeWeight: 0
      };
      var infowindow;
      var animationIntervalID;
      var animatedlines = new Array;
      var animate = true;

      var origin = null;
      var dest = null;
      var origlisten = null;
      var destlisten = null;
      var distline = null;

      function calcDist(event) {
        if (null != distline) {
          distline.setMap(null);
          distline = null;
        }
        if (null != infowindow) {
          infowindow.close();
          infowindow = null;
        }
        if (null != dest) {
          dest.setMap(null);
          dest = null;
        }
        if (null != destlisten) {
          google.maps.event.removeListener(destlisten);
          destlisten = null;
        }
        if (null == origin) {
          origin = new google.maps.Marker({
            position: event.latLng,
            title: "Starting point",
            draggable: true,
            map: map
          });
          origlisten = google.maps.event.addListener(origin, "dragend", function() {
            if (null != distline) {
              distline.setPath([origin.getPosition(), dest.getPosition()]);
              infowindow.setContent("<div style=\"font-size: 10pt; font-family: 'Arial, sans-serif'\"><b>Distance:</b><br/><br/>" +
                     Math.round(google.maps.geometry.spherical.computeDistanceBetween(origin.getPosition(), dest.getPosition())*100)/100 + " m</div>"
                     );
            }
          });
        } else {
          dest = new google.maps.Marker({
            position: event.latLng,
            title: "Ending point",
            draggable: true,
            map: map
          });
          distline = new google.maps.Polyline({
            path: [
              origin.getPosition(),
              dest.getPosition()
            ],
            strokeColor: "#FF0000",
            strokeOpacity: 1,
            clickable: false,
            zIndex: 5,
            map: map
          });

          infowindow = new google.maps.InfoWindow({
            content: "<div style=\"font-size: 10pt; font-family: 'Arial, sans-serif'\"><b>Distance:</b><br/><br/>" +
                     Math.round(google.maps.geometry.spherical.computeDistanceBetween(origin.getPosition(), dest.getPosition())*100)/100 + " m</div>"
          });

          infowindow.open(map, dest);

          destlisten = google.maps.event.addListener(dest, "dragend", function() {
            distline.setPath([origin.getPosition(), dest.getPosition()]);
            infowindow.setContent("<div style=\"font-size: 10pt; font-family: 'Arial, sans-serif'\"><b>Distance:</b><br/><br/>" +
                     Math.round(google.maps.geometry.spherical.computeDistanceBetween(origin.getPosition(), dest.getPosition())*100)/100 + " m</div>"
                     );
          });
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
        rulerUI.title = 'Click to measure distances';
        controlsDiv.appendChild(rulerUI);

        var rulerImg = document.createElement('div');
        rulerImg.style.width= '100%';
        rulerImg.style.height = '17px';
        rulerImg.style.backgroundImage = "url('images/ruler.png')";
        rulerImg.style.backgroundPosition = 'center center';
        rulerImg.style.backgroundRepeat = 'no-repeat';
        rulerUI.appendChild(rulerImg);


        var rulerListen = null;
        google.maps.event.addDomListener(rulerUI, 'click', function() {
          if (null == rulerListen) {
            rulerListen = google.maps.event.addListener(map, 'click', calcDist);
            rulerUI.style.boxShadow = 'inset 0 1px 4px rgba(0, 0, 0, 0.4)';
          } else {
            if (null != origlisten) google.maps.event.removeListener(origlisten);
            if (null != destlisten) google.maps.event.removeListener(destlisten);
            if (null != origin)   origin.setMap(null);
            if (null != dest)     dest.setMap(null);
            if (null != distline) distline.setMap(null);
            origlinsten = null;
            destlinsten = null;
            origin   = null;
            dest     = null;
            distline = null;

            google.maps.event.removeListener(rulerListen);
            rulerListen = null;
            rulerUI.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.4)';
          }
        });

        var animationsUI = document.createElement('div');
        animationsUI.style.cssText = rulerUI.style.cssText;
        animationsUI.style.minWidth = "86px";
        animationsUI.title = 'Disable Link Visibility Animations';
        controlsDiv.appendChild(animationsUI);

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

      }
      
      

      
      function Mid (mainip, aliasip) {
        alias[aliasip] = mainip;
        if ( null == mainipaliases[mainip]) {
          mainipaliases[mainip] = new Array;
        }
        mainipaliases[mainip].push(aliasip);
      }
     
     
      var offset = 0;
      function animateFun() {
        if (animate) {
          offset = offset + 5;
          for (var i = 0; i < animatedlines.length; i++) {
            var icons = animatedlines[i].get('icons');
            icons[0].offset = offset + "px";
            animatedlines[i].set("icons", icons);
          }
        }
      }
     
      
      function Node (mainip, lat, lon, ishna, hnaip, name) {
        points[mainip] = new google.maps.Marker({
          position: new google.maps.LatLng(lat, lon),
          icon: greenpoint,
          title: name,
          map: map
        });
        mainipnames[mainip] = name;
     
     
        google.maps.event.addListener(points[mainip], "click", function() {
          if (null != infowindow) {
            infowindow.close();
            infowindow = null;
          }

          infowindow = new google.maps.InfoWindow({
            content: "<div style=\"font-size: 10pt; font-family: 'Arial, sans-serif'\"><b>" + name + "</b><br/><br/>" +
                     "<b>Node IPs:</b> "+ mainip + 
                       (null == mainipaliases[mainip]?"":", " + mainipaliases[mainip]) + "<br/>" +
                       (null == mainiplinks[mainip]?"" :
                         "<b>Neighbours:</b><br/>" +
                         "<table style='border: 0px; padding-left:2em; white-space: nowrap;'><tr>" + mainiplinks[mainip].join("</tr><tr>") + "</tr></table>"
                       ) +
                     "</div>"
          });

          infowindow.open(map, points[mainip]);
        });

        google.maps.event.addListener(points[mainip], "mouseover", function() {
          for (var i = 0; i < mainiplines[mainip].length; i++) {
            animatedlines.push(new google.maps.Polyline({
              path: mainiplines[mainip][i].getPath(),
              strokeOpacity: 0,
              icons: [{
                icon: { path: 'M 0, -1 0, 2', strokeOpacity: 1, strokeColor: "#00FF00", scale: 3 },
                offset: '0',
                repeat: '20px'
              }],
              zIndex: 10,
              map: map
            }));

          }
          animationIntervalID = window.setInterval(animateFun, 500);
        });

        google.maps.event.addListener(points[mainip], "mouseout", function() {
          for (var i = 0; i < animatedlines.length; i++) {
            animatedlines[i].setMap(null);
            animatedlines[i] = null;
          }
          animatedlines = new Array;
          window.clearInterval(animationIntervalID);
        });
        bounds.extend(points[mainip].getPosition());
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
        if (null != points[fromip] && null != points[toip]) {

          if ( null == mainiplinks[fromip] ) {
            mainiplinks[fromip] = new Array;
          }
          mainiplinks[fromip].push("<td>" + mainipnames[toip] + ":</td><td> LQ: " + lq + "</td><td> NLQ: " + nlq + "</td><td> ETX: " + etx + "</td>");

          //Add a line between the two points
          if (null == mainiplines[fromip]) {
            mainiplines[fromip] = new Array;
          }
          mainiplines[fromip].push(new google.maps.Polyline({
            path: [
              points[fromip].getPosition(),
              points[toip].getPosition()
            ],
            strokeColor: "#FF0000",
            strokeOpacity: 0.5,
            clickable: false,
            zIndex: 5,
            map: map
          }));
        } else {
          if (null == points[toip]) {
            unkpos[toip] = mainipnames[toip];
            if (null == unkpos[toip]) {
              unkpos[toip] = toip;
            }
          }
          if (null == points[fromip]) {
            unkpos[fromip] = mainipnames[toip];
            if (null == unkpos[fromip]) {
              unkpos[fromip] = fromip;
            }
          }
        }
        lineid++;
      }
      
      function PLink (fromip, toip, lq, nlq, etx, lata, lona, ishnaa, latb, lonb, ishnab) {
        Link(fromip, toip, lq, nlq, etx);
      }
      
      function initialize () {
        var mapOptions = {
          mapTypeId: google.maps.MapTypeId.ROADMAP,
        };

        map = new google.maps.Map(document.getElementById("map"), mapOptions);
        bounds = new google.maps.LatLngBounds();

        var controls = document.createElement('div');
        controls.index = -1;
        buildControls(controls);

        map.controls[google.maps.ControlPosition.TOP_RIGHT].push(controls);
        
        var INFINITE = 99.9;
        /*
        Let the dump go here
        */
        <?php
          if(file_exists($latlonfile)) {
            $file = fopen($latlonfile, "r");
            echo  fread($file,500000);
	  }
        ?>

        if (bounds.isEmpty()) {
          map.setCenter(new google.maps.LatLng(39.6183836, -8.8302612));
          map.setZoom(11);
        } else {
          map.fitBounds(bounds);
        }
      }
    </script>
  </head>
  <body onLoad="initialize()">
    <div id="map" style="width: 100%; height: 100%"></div>
  </body>
</html>
