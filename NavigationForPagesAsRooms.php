<?php
$wgExtensionFunctions[] = 'efNavigationForPagesAsRooms';
 
function efNavigationForPagesAsRooms() {
    global $wgParser;
    $wgParser->setHook( 'navigation', 'efRenderNavigationLine' );
}

$castle_navigation = array(
"ancient looking scrolls" => "step back into [[The Castle Entrance]].",
"armourer's tower" => "go back into the [[inner ward]], or go up to the [[atilliator's workshop]].",
"around thunder rabbit's carrot" => "go inside [[Thunder Rabbit's Carrot]] (where you can relax without sinking into the cloud), go back to [[the royal garden]], or get lost in [[the Alien Forest]].",
"astronomical tower" => "go up to the [[candle makers' shop]], or back into the [[inner ward]].",
"astronomical tower:observatory" => "go down to the [[candle makers' shop]], or west to [[Soness Soleil Solarium]].",
"atilliator's workshop" => "go down to the [[armourer's tower]], south to [[the barracks]], or west to [[the parlour]].",
"bake house" => "go back into the [[inner ward]], or pass through to the [[granary]].",
"balcony overlooking the pool" => "jump into [[the swimming pool]], or go back inside the [[east upper hall]].",
"balcony overlooking the roller coaster" => "go back into the [[south upper corridor]], or down the ladder to [[The Roller Coaster area]].",
"bland hall" => "teleport into the [[grand hall]].",
"bronwyn hall" => "go back into the [[inner ward]].",
"buttery" => "go west to the [[grand hall]], or east to the [[main kitchen]].",
"candle makers' shop" => "go up to the [[Astronomical Tower:Observatory|Observatory]], down to the [[Astronomical Tower|first floor]], or west to the [[south servants' quarters]].",
"castle entry path" => "step off the path and get lost in [[the plain cloud plain]], walk north to the [[dragon parking area]], or walk south to [[the drawbridge]].",
"central upper corridor" => "go south to the [[south upper corridor]], or north to the [[north upper corridor]].",
"central upper hall" => "descend the [[next grand staircase]], go west into the [[west upper hall]], or east into the [[east upper hall]].",
"chillaxation room" => "go back out to [[Meric's Game Room]].",
"dragon parking area" => "walk south along the [[castle entry path]], or take your dragon and fly away from here.  Larger dragons may need to use the [[dragon run-way]].",
"dragon run-way" => "taxi your dragon to the  [[dragon parking area]], or use the ample space here to take off and fly back to Destria.",
"drawing room" => "ascend the [[next grand staircase]], descend [[the grand staircase]], go east to [[the parlour]], or west to the [[music room]]",
"east upper hall" => "go west into the [[central upper hall]], or outside onto the [[balcony overlooking the pool]].",
"ellis chapel" => "go back into the [[inner ward]], or step into the [[sacristy]].",
"endless moat" => "walk east along the [[endless moat]], or walk west along the [[endless moat]].",
"entrance to the roller coaster" => "ride [[The Roller Coaster/initial ascent|The Roller Coaster]], or get scared and continue to walk around [[the Roller Coaster area]].",
"equally grand staircase" => "ascend to [[The Castle Entrance]], or descend to the [[main hallway]].",
"fricke's restaurant" => "go back to [[Meric's Game Room]], or one-way out to [[the pool area]].",
"fricke's wet bar" => "keep patronizing [[Fricke's wet bar]], or swim around in [[the swimming pool]].",
"gallery overlooking the grand hall" => "go west into [[the solar]], east to the [[south servants' quarters]], or take the stairs down to the [[grand hall]].",
"granary" => "go back into the [[inner ward]], or pass through to the [[bake house]].",
"grand hall" => "take the stairs up to [[the solar]] and the [[gallery overlooking the grand hall|gallery]], go east through the [[buttery]] toward the main kitchen, or back into the [[inner ward]].",
"inner ward" => "go north to [[The Castle Entrance]], [[the stables]], or the [[senior servants' quarters]].  You can go south to the [[main kitchen]], or the [[grand hall]].  While on the east you see the [[place of arms]], the [[granary]], and the [[bake house]], and on the west you see [[Ellis Chapel]], and [[Bronwyn Hall]], you can also visit any of the four towers in the corners of the inner ward: the [[Astronomical Tower]], [[Watanabe Tower]], the [[armourer's tower]] or even the [[prison tower]]!",
"main east hallway" => "go west to the [[main hallway]], or clean up in [[the showers]].",
"main hallway" => "ascend the [[equally grand staircase]], go east to the [[main east hallway]], west to the [[main west hallway]], or play some games in [[Meric's Game Room]].",
"main kitchen" => "go out to the [[inner ward]], up to the [[south servants' quarters]], walk into the [[pantry]], or go through the [[buttery]] toward the grand hall.",
"main west hallway" => "go east to the [[main hallway]], down to [[the wine cellar]], or out to the [[West Side]].",
"meric's game room" => "go north to the [[main hallway]], south to the [[movie theater]], west to the [[chillaxation room]], or get some food in [[Fricke's Restaurant]].",
"entrance to the roller coaster/onboard" => "wish you had signed a will while you wait to [[The Roller Coaster/initial ascent|begin the ride]].",
"the roller coaster/initial ascent" => "enjoy [[The Roller Coaster/initial descent|initial descent]].",
"the roller coaster/initial descent" => "eat lunch during the [[The Roller Coaster/second ascent|second ascent]].",
"the roller coaster/second ascent" => "finish riding [[The Roller Coaster]].",
"movie theater" => "go back to [[Meric's Game Room]].",
"music room" => "go east to the [[drawing room]], or go west to the [[north servants' quarters]].",
"next grand staircase" => "ascend to the [[central upper hall]], or descend to the [[drawing room]].",
"north servants' quarters" => "go down to the [[senior servants' quarters]], east to the [[music room]], or west to the [[prison tower guard quarters]].",
"north upper corridor" => "go south to the [[central upper corridor]], or north to the [[west upper hall]].",
"pantry" => "go back into the [[main kitchen]].",
"place of arms" => "go up into [[the barracks]], or go out into the [[inner ward]].",
"prison tower guard quarters" => "take the stairs down to the [[prison tower|ground level]], go east to the [[north servants' quarters]], or south to [[the library]].",
"prison tower" => "go back into the [[inner ward]], take the stairs up to the [[prison tower guard quarters|guard quarters]], or jump down into [[the dungeon]].",
"sacristy" => "go back into [[Ellis Chapel]].",
"senior servants' quarters" => "go back into the [[inner ward]], or go up to the [[north servants' quarters]].",
"soness soleil solarium" => "go east to [[Astronomical Tower:Observatory|the Observatory]].",
"south servants' quarters" => "go down to the [[main kitchen]], east to the [[candle makers' shop]], or west to the [[gallery overlooking the grand hall]].",
"south upper corridor" => "go north to the [[central upper corridor]], out onto the [[balcony overlooking The Roller Coaster]], or into [[Rob's bedroom]] across from the balcony.",
"rob's bedroom" => "go back out into the [[South upper corridor]].",
"the alien forest" => "get further lost in [[the Alien Forest]].",
"the barracks" => "go down to the [[place of arms]], or north to the [[atilliator's workshop]].",
"the castle entrance" => "ask Sebastian for one of the [[ancient looking scrolls|scrolls]], ascend [[the grand staircase]], descend the [[equally grand staircase]], go in to the [[inner ward]], or out to [[the drawbridge]].",
"the drawbridge" => "enter [[The Castle Entrance|The Castle]], go north along the [[castle entry path]], or jump down into the [[endless moat]].",
"the dungeon" => "hope someone helps you out of [[the dungeon]].  Good luck with that!",
"the grand staircase" => "ascend to the [[drawing room]], or descend to [[The Castle Entrance]].",
"the jacuzzi" => "keep chillin in [[the jacuzzi]], or get out to [[the pool area]].",
"the library" => "relax in [[the library]], go south along the [[upper nave]] of Ellis Chapel to [[Watanabe Tower:2F guard quarters|guard quarters of Watanabe Tower]], or go north to the [[prison tower guard quarters]].",
"the parlour" => "go west to the [[drawing room]], or east to the [[atilliator's workshop]].",
"the plain cloud plain" => "go further into [[the plain cloud plain]].",
"the pool area" => "step into [[the jacuzzi]], jump into [[the swimming pool]], go inside to [[the showers]], or out onto [[the sun deck]].",
"the rear courtyard" => "go south to [[the royal garden]], or north into the [[grand hall]].",
"the roller coaster area" => "go up the ladder to the [[balcony overlooking The Roller Coaster]], go north to the [[West Side]], around to the [[entrance to The Roller Coaster]], or get confused by [[the Windy Woods]].",
"the roller coaster exit" => "proudly walk around [[the Roller Coaster area]].",
"the roller coaster" => "stumble dazedly to [[The Roller Coaster exit]].",
"the royal garden" => "go north to [[the rear courtyard]], or east toward [[Around Thunder Rabbit's Carrot|Thunder Rabbit's Carrot]].",
"the showers" => "go outside to [[the pool area]], or north into the [[main east hallway]].",
"the solar" => "go east into the [[gallery overlooking the grand hall|gallery]], or take the stairs down to the [[grand hall]].",
"the stables" => "go back into the [[inner ward]].",
"the sun deck" => "go back to [[the pool area]], or step foolishly out into [[the plain cloud plain]].",
"the swimming pool" => "swim around [[the swimming pool]], climb out to [[the pool area]], or get a snack or drink at [[Fricke's wet bar]].",
"the windy woods" => "feel the warm embrace of [[the Windy Woods]].",
"the wine cellar" => "keep sampling wines in [[the wine cellar]], or go up to the [[main west hallway]].",
"thunder rabbit's carrot" => "stay and read more scrolls: about the [[historical scroll:on how Thunder Rabbit's Carrot was built|construction of TR's Carrot]], or [[historical scroll:on how to make a laser out of a tin can and a piece of grass|making a laser]]. \n\nWhen you're ready, you can go [[around Thunder Rabbit's Carrot|outside]].",
"upper nave" => "have a seat in the [[upper nave]] and watch the service in Ellis Chapel, or take the passage north to [[the library]] or south to [[Watanabe Tower:2F guard quarters|guard quarters of Watanabe Tower]].",
"watanabe tower" => "go back into the [[inner ward]], or take the stairs up to the [[Watanabe Tower:2F guard quarters|guard quarters]].",
"watanabe tower:2f guard quarters" => "take the stairs [[Watanabe Tower|down to the ground level]], or go north along the [[upper nave]] of Ellis Chapel to [[the library]].",
"west side" => "go east into the [[main west hallway]], or south to [[The Roller Coaster area]].",
"west upper hall" => "go east into the [[central upper hall]], or south into the [[north upper corridor]].",

"on the nature of the cloud" => "You can [[The library|put the book down]].",
"otnotc - in, off, out" => "[[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - On again, and Off again|On again, and Off again >]]",
"otnotc - on again, and off again" => "[[Library:OtnoTC - In, Off, Out|< In, Off, Out]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - Back Into the Cave|Back Into the Cave >]]",
"otnotc - back into the cave" => "[[Library:OtnoTC - On again, and Off again|< On again, and Off again]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - Plan with Scout Seven|Plan with Scout Seven >]]",
"otnotc - plan with scout seven" => "[[Library:OtnoTC - Back Into the Cave|< Back Into the Cave]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - Scuba in the Cave|Scuba in the Cave >]]",
"otnotc - scuba in the cave" => "[[Library:OtnoTC - Plan with Scout Seven|< Plan with Scout Seven]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - The Cave is Forbidden|The Cave is Forbidden >]]",
"otnotc - the cave is forbidden" => "[[Library:OtnoTC - Scuba in the Cave|< Scuba in the Cave]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - Flying Plan|Flying Plan >]]",
"otnotc - flying plan" => "[[Library:OtnoTC - The Cave is Forbidden|< The Cave is Forbidden]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - Secretly through|Secretly through >]]",
"otnotc - secretly through" => "[[Library:OtnoTC - Flying Plan|< Flying Plan]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - Off to Court with you!|Off to Court with you! >]]",
"otnotc - off to court with you!" => "[[Library:OtnoTC - Secretly through|< Secretly through]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - Inspecting the Fi re ba ts|Inspecting the Fi re ba ts >]]",
"otnotc - inspecting the fi re ba ts" => "[[Library:OtnoTC - Off to Court with you!|< Off to Court with you!]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - Sebastian takes the stand|Sebastian takes the stand >]]",
"otnotc - sebastian takes the stand" => "[[Library:OtnoTC - Inspecting the Fi re ba ts|< Inspecting the Fi re ba ts]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - Newsflash!|Newsflash! >]]",
"otnotc - newsflash!" => "[[Library:OtnoTC - Sebastian takes the stand|< Sebastian takes the stand]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - In the beginning...|In the beginning... >]]",
"otnotc - in the beginning..." => "[[Library:OtnoTC - Newsflash!|< Newsflash!]] | [[Library:On the nature of The Cloud|Chapter Index]] | [[Library:OtnoTC - Cloud vs Crowd|Cloud vs Crowd >]]",
"otnotc - cloud vs crowd" => "[[Library:OtnoTC - In the beginning...|< In the beginning...]] | [[Library:On the nature of The Cloud|Chapter Index]]",

"thunder rabbit" => "With the Magic of The Wiki, you can teleport to [[Thunder Rabbit's Carrot]]",


);

$_navigation_array_array = $castle_navigation;
 
function efRenderNavigationLine( $input, $args, $parser ) {
	global $_navigation_array_array;
	$roomTitle = $parser->mTitle->mTextform;

	$prefix = "You can ";
	$postfix = ""; //  (while laying out the pages, remember to add &lt;navigation/&gt;)";

	if(!array_key_exists(strtolower($roomTitle), $_navigation_array_array))
	{
		switch($parser->mTitle->getNamespace())
		{
			case 100:    // namespace = scroll
				$where_we_can_go = "look for another [[ancient looking scrolls|another scroll]], or step back into [[The Castle Entrance]].";
				break;

			default:
				$where_we_can_go = "There is nowhere to go from [[$roomTitle]].  Tell [[Castlepedia:Castle Workers|The Castle Workers]] to get busy!";
				$prefix = "";   // You can see why we don't need "You can " at the beginning of the sentence
				break;
		}
	}
	else
	{
		switch($parser->mTitle->getNamespace())
		{
			case 106:    // namespace = library
				$prefix = "<hr/><br/>";   // horizontal line and newline
			break;
			case 104:   // castlepedia (for biographies in castlepedia, we will allow them to teleport to their rooms ("With The Magick of Wiki")
				$prefix = "";   // no prefix, please
			break;
			default:
		}
		$where_we_can_go = $_navigation_array_array[strtolower($roomTitle)];
	}

	$exit_directions = $parser->recursiveTagParse($where_we_can_go);

	return $prefix . $exit_directions . $postfix;
}
?>