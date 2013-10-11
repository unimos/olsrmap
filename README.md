olsrmap
=======

This map will show all geo-locatable nodes currently visible on an OLSR network.

### Why YET another OLSR map? ###

The Freifunk firmware included two types of maps:  
 * a centralized map hosted on a single server and updated on demand by each node. This shows a map with all nodes that have been seen since a given time (e.g.: last two days);
 * a distributed map where each node can show what other nodes it can see on the network.

The centralized map can be found at [Layereight's](http://www.layereight.de/software.php#freifunk) website.  
The haserlmap, a modified version of the distributed map can be found at [Augsburg's Trac site](http://trac.augsburg.freifunk.net/browser/contrib/haserlmap).  
The olsrmap is inspired by both these approaches and tries to modernise them.

Looking around at the available OLSR maps, none of them seemed to have all features we needed, so I just created a new one.

#### The olsrmap ####
 * Displays all nodes on an OLSR network that share their location;
 * Displays all current links between these nodes;
 * Features measuring tools for easy:
   * distance determination;
   * coordinate determination;
   * terrain profile (orography) between two points;
   * determination of the magnetic bearing between two points;
 * Uses Google Maps;
 * Can be installed on a central/Internet-accessible server;
 * Can be used on any OLSR node (e.g.: an OpenWrt wireless router).

##### Requirements #####
* Lattitude and longitude parameters configured on each OLSR node;
* A web server with PHP enabled — see the easy [PHP HOWTO on the OpenWrt wiki](http://wiki.openwrt.org/doc/howto/php) if you intend to use this on OpenWrt;
* A latlon.js output file from the nameservice plugin must be accessible by the web server (either locally or from an OLSR node).
