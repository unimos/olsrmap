

<?php
  //The location where the OLSR daemon dumps the latlon file
  $latlonfile="/var/run/latlon.js";
?>

<!DOCTYPE html>
<html>
  <head>
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
    <script type="text/javascript">
      var map;
      var bounds; //Automagically defines the best bounds for the map

      var point = new Object();//"Associative Array" with all data from all nodes
      var alias = new Object();//"Associative Array" with the main ip of each alias

      var greenpoint = {
          path: google.maps.SymbolPath.CIRCLE,
          fillColor: "#7ac142",
          fillOpacity: 1,
          scale: 5,
          strokeWeight: 0
      };
      var infowindow;
      var animationIntervalID;
      var animatedlines = new Array();
      var animate = true;

      var origin = null;
      var dest = null;
      var origlisten = null;
      var destlisten = null;
      var distline = null;

      //TODO: This is awful. Improve this code!
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
              infowindow.open(map, dest);
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
              infowindow.open(map, dest);
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
          content: "<div style=\"font-size: 10pt; font-family: 'Arial, sans-serif'\"><b>" + point[ip].name + "</b><br/><br/>" +
                   "<b>Node IPs:</b> "+ ip +
                     (null == point[ip].aliases.length > 0? "" : ", " + point[ip].aliases) + "<br/>" +
                     (null == point[ip].links.length   > 0? "" : "<b>Neighbours:</b><br/>" + getNeighboursTable(ip)) +
                   "</div>"
        });

        infowindow.open(map, point[ip].marker);
      }


      /*
       * The animated lines that show up when hovering a node
       */
      function animateNodeLines (ip) {
        for (var i = 0; i < point[ip].lines.length ; i++) {
          animatedlines.push(new google.maps.Polyline({
            path: point[ip].lines[i].getPath(),
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

        var offset = 0;
        animationIntervalID = window.setInterval(
          function animateFun() {
            if (animate) {
              offset = offset + 2;
              for (var i = 0; i < animatedlines.length; i++) {
                var icons = animatedlines[i].get('icons');
                icons[0].offset = offset + "px";
                animatedlines[i].set("icons", icons);
              }
            }
          }
          , 500);
      }

      function stopAnimatingNodeLines(ip) {
        for (var i = 0; i < animatedlines.length; i++) {
            animatedlines[i].setMap(null);
            animatedlines[i] = null;
        }
        animatedlines = new Array();
        window.clearInterval(animationIntervalID);
      }


      /*
       * All information we gather for each node
       */
      function OLSRNode (mainip, lat, lon, name) {
        this.name  = name;
        this.ip    = mainip;
        this.aliases = new Array();
        this.lat   = lat;
        this.lon   = lon;
        this.links = new Array();
        this.hasCoords = false;

        this.setCoords = function(setLat, setLon) {
          this.lat = setLat;
          this.lon = setLon;

          this.marker = new google.maps.Marker({
              position: new google.maps.LatLng(setLat, setLon),
              icon: greenpoint,
              title: this.name,
              map: map
          });

          google.maps.event.addListener(this.marker, "click",     function() { showNodeDetailWindow(mainip);   });
          google.maps.event.addListener(this.marker, "mouseover", function() { animateNodeLines(mainip);       });
          google.maps.event.addListener(this.marker, "mouseout",  function() { stopAnimatingNodeLines(mainip); });

          bounds.extend(this.marker.getPosition());
          this.hasCoords = true;
        };
        if (lat != null && lon != null) {
          this.setCoords(lat, lon);
        }
        this.lines = new Array();// [google.maps.Polyline]

      }


      /*
       * Node dumping functions
       */
      function Mid (mainip, aliasip) {
        alias[aliasip] = mainip;
        if ( null == point[mainip]) {
          point[mainip] = new OLSRNode(mainip, null, null, null);//isn't there a better way?
        }
        point[mainip].aliases.push(aliasip);
      }


      function Node (mainip, lat, lon, ishna, hnaip, name) {
        if (null == point[mainip]) {
          point[mainip] = new OLSRNode(mainip, lat, lon, name);
        } else {
          point[mainip].name = name;
          point[mainip].setCoords(lat, lon);
        }
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
            etx: etx
          });

          //Add a line between the two points
          point[fromip].lines.push(new google.maps.Polyline({
            path: [
              point[fromip].marker.getPosition(),
              point[toip].marker.getPosition()
            ],
            strokeColor: "#FF0000",
            strokeOpacity: 0.5,
            clickable: false,
            zIndex: 5,
            map: map
            })
          );
        } else {
          //TODO: Add debug code here
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
      }
    </script>
  </head>
  <body onLoad="initialize()">
    <div id="map" style="width: 100%; height: 100%"></div>
  </body>
</html>
