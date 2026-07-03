-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 03, 2026 at 09:23 PM
-- Server version: 11.8.6-MariaDB-0+deb13u1 from Debian
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `freetv`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `collector` varchar(255) DEFAULT NULL,
  `offline` tinyint(1) NOT NULL DEFAULT 0,
  `appdata` tinyint(1) NOT NULL DEFAULT 0,
  `showads` tinyint(1) NOT NULL DEFAULT 0,
  `modules` tinyint(1) NOT NULL DEFAULT 0,
  `debugmode` tinyint(1) NOT NULL DEFAULT 0,
  `lastupdated` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`id`, `collector`, `offline`, `appdata`, `showads`, `modules`, `debugmode`, `lastupdated`, `created_at`, `updated_at`) VALUES
(1, 'https://freetv.today/api/beacon.php', 0, 0, 0, 1, 0, '2025-10-05 18:53:30', '2026-07-03 15:51:06', '2026-07-03 15:51:06');

-- --------------------------------------------------------

--
-- Table structure for table `playlists`
--

CREATE TABLE `playlists` (
  `id` int(10) UNSIGNED NOT NULL,
  `filename` varchar(255) NOT NULL,
  `dbtitle` varchar(255) NOT NULL,
  `dbversion` varchar(50) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `lastupdated` datetime DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `playlists`
--

INSERT INTO `playlists` (`id`, `filename`, `dbtitle`, `dbversion`, `author`, `email`, `link`, `lastupdated`, `is_default`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'freetv.json', 'Default TV Shows', '1.0', 'Free TV', 'support@freetv.today', 'https://freetv.today', '2026-07-01 20:39:06', 1, 0, '2026-07-03 15:51:06', '2026-07-03 15:51:06'),
(2, 'ftv-british.json', 'British TV', '1.0', 'Free TV', 'support@freetv.today', 'https://freetv.today', '2026-07-01 02:08:40', 0, 1, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(3, 'ftv-holidays.json', 'Holiday Shows', '1.0', 'Free TV', 'support@freetv.today', 'https://freetv.today', '2025-10-08 18:08:16', 0, 2, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(4, 'ftv-movies.json', 'Movies', '1.0', 'Free TV', 'support@freetv.today', 'https://freetv.today', '2026-07-01 12:37:11', 0, 3, '2026-07-03 15:51:14', '2026-07-03 15:51:14');

-- --------------------------------------------------------

--
-- Table structure for table `playlist_shows`
--

CREATE TABLE `playlist_shows` (
  `id` int(10) UNSIGNED NOT NULL,
  `playlist_id` int(10) UNSIGNED NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `identifier` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_year` varchar(20) DEFAULT NULL,
  `end_year` varchar(20) DEFAULT NULL,
  `imdb` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `playlist_shows`
--

INSERT INTO `playlist_shows` (`id`, `playlist_id`, `category`, `status`, `identifier`, `title`, `description`, `start_year`, `end_year`, `imdb`, `sort_order`, `thumbnail_path`, `created_at`, `updated_at`) VALUES
(1, 1, 'animation', 'active', 'courage-the-cowardly-dog-1080p-ai-upscale', 'Courage The Cowardly Dog', 'The offbeat adventures of Courage, a cowardly dog who must overcome his own fears to heroically defend his unknowing farmer owners from all kinds of dangers, paranormal events and menaces that appear around their land.', '1999', '2002', 'tt0220880', 0, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(2, 1, 'animation', 'active', 'drkatz-disc-2', 'Dr. Katz, Professional Therapist', 'A therapist struggles with problems of his patients, while dealing with the ones in his personal life.', '1995', '2002', 'tt0111942', 1, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(3, 1, 'animation', 'active', 'DrawnTogetherComplete', 'Drawn Together', 'A parody of reality shows cast with spoofs of several famous types of animated characters.', '2004', '2007', 'tt0386180', 2, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(4, 1, 'animation', 'active', 'freakazoid_202210', 'Freakazoid!', 'Dexter Douglas of Washington, D.C. received the Pinnacle chip as a gift. He went online, but a flaw in the chip sucked him in and filled his head with all the information on the \'net, but he became very silly. Now he fights evil with urbane jokes.', '1995', '1997', 'tt0111970', 3, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(5, 1, 'animation', 'active', 'gargoyles-1080p-ai-upscale', 'Gargoyles', 'A clan of heroic night creatures pledge to protect modern New York City as they did in Scotland one thousand years earlier.', '1994', '1997', 'tt0108783', 4, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(6, 1, 'animation', 'active', 'one-crazy-summer_202408', 'Gravity Falls', 'Twin siblings Dipper and Mabel Pines spend the summer at their great-uncle\'s tourist trap in the enigmatic Gravity Falls, Oregon.', '2012', '2016', 'tt1865718', 5, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(7, 1, 'animation', 'active', 'the-grim-adventures-of-billy-and-mandy_202212', 'Grim Adventures of Billy & Mandy', 'The ill-tempered Grim Reaper gets into a wager that forces him to become the life-long companions of two scheming youngsters after he loses.', '2003', '2007', 'tt0292800', 6, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(8, 1, 'animation', 'active', 'home-movies', 'Home Movies', 'Brendon Small, an ambitious eight-year-old filmmaker, shoots movies with his two best friends.', '1999', '2004', 'tt0197159', 7, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(9, 1, 'animation', 'active', 'Reboot-HD', 'Reboot', 'The adventures of a Guardian named Bob and his companions Enzo and Dot Matrix as they work to keep Mainframe safe from the viruses and other threats.', '1994', '2001', 'tt0108903', 8, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(10, 1, 'animation', 'active', 'Spider-Man-67-Collection', 'Spider-Man (1967)', 'Original cartoon series based on the web-slinging Marvel comic book character, Peter Parker, who, after being bit by a radioactive spider, assumes extraordinary powers.', '1967', '1970', 'tt0061301', 9, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(11, 1, 'animation', 'active', 'spider-man-unlimited-1999-2001', 'Spider-Man Unlimited', 'Spider-Man travels to Counter-Earth to rescue a Terran shuttle crew trapped there and discovers a tyrannical and warped version of his world.', '1999', '2001', 'tt0207120', 10, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(12, 1, 'animation', 'active', 'StarWarsCloneWars2003', 'Star Wars: Clone Wars', 'The events and battles of the Galactic Republic\'s last major war are recounted.', '2003', '2005', 'tt0361243', 11, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(13, 1, 'animation', 'active', 'The.adventures.of.Tintin.animated.series', 'The Adventures of Tintin', 'The adventures of the young reporter, his faithful dog and friends as they travel around the world on adventures.', '1991', '1992', 'tt0179552', 12, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(14, 1, 'animation', 'active', 'the-boondocks-complete', 'The Boondocks', 'Brothers Huey and Riley Freeman experience a culture clash when they leave Chicago to move in with their grandfather in the suburbs.', '2005', '2014', 'tt0373732', 13, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(15, 1, 'animation', 'active', 'the-incredble-hulk-1966-complete-series-english', 'The Incredible Hulk (1966)', 'The adventures of a nuclear scientist cursed with the tendency to turning into a huge green brute under stress.', '1966', '1966', 'tt0206488', 14, NULL, '2026-07-03 15:51:07', '2026-07-03 15:51:07'),
(16, 1, 'animation', 'active', 'The_Maxx', 'The Maxx', 'Confused hulking homeless superhero The Maxx tries to protect his social worker and friend Julie from an omniscient serial killer Mr. Gone both in the real world, which may or may not actually be real, and the subconscious fantasy world.', '1995', '1995', 'tt0112065', 15, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(17, 1, 'animation', 'active', 'x-men-the-animated-series-1080p-ai-upscale_202204', 'X-Men', 'A team of mutant superheroes fight for justice and human acceptance in the Marvel Comics universe.', '1992', '1997', 'tt0103584', 16, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(18, 1, 'anime', 'active', 'db-bebop-of-the-cowboys-1080p', 'Cowboy Bebop', 'The futuristic misadventures and tragedies of an easygoing bounty hunter and his partners.', '1998', '1999', 'tt0213338', 17, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(19, 1, 'anime', 'active', 'digimon-data-squad-the-complete-series', 'Digimon Data Squad', 'We pick up with our heroes and the challenges faced by the members of DATS (\"Digital Accident Tactics Squad\"), an organization created to conceal the existence of the Digital World and Digimon from the rest of mankind.', '2006', '2008', 'tt1138300', 18, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(20, 1, 'anime', 'active', 'g-force-guardians-of-space', 'G-Force', 'G-Force: Guardians of Space - a bird-themed superhero team battles the threat of Galactor and his minions.', '1986', '1987', 'tt0302109', 19, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(21, 1, 'anime', 'active', 'hamtaro_complete_series', 'Hamtaro', 'The story of hamsters who get together at meetings to talk about their adventures.', '2000', '2006', 'tt0318895', 20, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(22, 1, 'anime', 'active', 'legend-of-zelda-1989-complete-series', 'Legend Of Zelda', 'Link and Princess Zelda protect the mystical artifact, the Triforce, from falling into the hands of evil sorcerer Ganon.', '1988', '1989', 'tt0832330', 21, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(23, 1, 'anime', 'active', 'neon-genesis-evangelion_1080p_adv_1996', 'Neon Genesis Evangelion', 'A teenage boy finds himself recruited as a member of an elite team of pilots by his father. Original title: Shinseiki Evangelion.', '1995', '1996', 'tt0112159', 22, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(24, 1, 'anime', 'active', 'pokemon-advanced-battle-the-complete-collection-2005-06-english-dub', 'Pokémon: Advanced Battle', 'Pokémon: Advanced Battle is the eighth season of Pokémon and the third season of Pokémon the Series: Ruby and Sapphire, known in Japan as Pocket Monsters: Advanced Generation.', '2005', '2006', 'tt0168366', 23, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(25, 1, 'anime', 'active', 'pokemon-advanced-challenge-the-complete-collection-2004-05-english-dub', 'Pokémon: Advanced Challenge', 'Pokémon: Advanced Challenge is the seventh season of Pokémon and the second season of Pokémon the Series: Ruby and Sapphire, known in Japan as Pocket Monsters: Advanced Generation', '2004', '2005', 'tt0168366', 24, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(26, 1, 'anime', 'active', 'pokemon-xy-kalos-quest-the-complete-collection-tpci-english-dub', 'Pokémon: XY Kalos Quest', 'Pokémon the Series: XY Kalos Quest is the eighteenth season of the Pokémon anime series, and the second season of Pokémon the Series: XY, known in Japan as Pocket Monsters: XY. Originally aired from February 7 to December 19, 2015.', '1997', '2023', 'tt0168366', 25, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(27, 1, 'anime', 'active', 'sonic-x-complete-series-discotek-media-english-language-collection-bluray-rips-mkv', 'Sonic X', 'A slight malfunction causes Chaos Control, and sends Sonic the Hedgehog to Earth. While there, Sonic meets Chris Thorndyke, who aids at collecting the Chaos Emeralds, so Sonic and friends can go home.', '2003', '2006', 'tt0367413', 26, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(28, 1, 'anime', 'active', 'yu-gi-oh-the-complete-series', 'Yu-Gi-Oh!', 'Yugi Moto solves an Ancient Egyptian Puzzle and brings forth a dark and powerful alter ego. Whenever he and his friends are threatened by evil in Duel Monster Card Game, this alter ego breaks out to save them.', '2000', '2006', 'tt0249327', 27, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(29, 1, 'cartoons', 'active', 'andy-panda-filmography', 'Andy Panda', 'Andy Panda is a cartoon character who starred in his own series of animated cartoon short subjects produced by Walter Lantz. These \"cartunes\" were released by Universal Pictures from 1939 to 1947 and United Artists from 1948 to 1949.', '1939', '1949', 'tt0141550', 28, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(30, 1, 'cartoons', 'active', 'barney-bear-1939-xvid', 'Barney Bear', 'Barney Bear is an American series of animated cartoon short subjects produced by Metro-Goldwyn-Mayer cartoon studio. The title character is an anthropomorphic cartoon character, a sluggish, sleepy bear who often is in pursuit of peace and quiet.', '1939', '1954', 'tt0036626', 29, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(31, 1, 'cartoons', 'active', 'captain-caveman-and-the-teen-angels', 'Capt. Caveman & the Teen Angels', 'The adventures of a superhero caveman and a trio of female amateur detectives.', '1977', '1980', 'tt0284712', 30, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(32, 1, 'cartoons', 'active', 'c.-b.-bears-complete-series-1977', 'CB Bears', '\"The CB Bears\" segment follows Hustle, Boogie and Bump, three anthropomorphic bear detectives posing as garbagemen, who solve mysteries.', '1977', '1977', 'tt0075487', 31, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(33, 1, 'cartoons', 'active', 'chilly-willy-filmography', 'Chilly Willy', 'Chilly Willy is a penguin cartoon character who was starred in a series set of 50 theatrical shorts from 1953 to 1972. He became the second most popular Lantz/Universal character after Woody Woodpecker.', '1953', '1972', 'tt0140895', 32, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(34, 1, 'cartoons', 'active', 'dexters-laboratory-the-complete-series', 'Dexter\'s Laboratory', 'The misadventures of a boy genius and his annoying sister.', '1996', '2003', 'tt0115157', 33, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(35, 1, 'cartoons', 'active', 'fat-albert-72', 'Fat Albert & the Cosby Kids', 'The educational adventures of a group of Afro-American inner city kids.', '1972', '1985', 'tt0068072', 34, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(36, 1, 'cartoons', 'active', 'garfield-and-friends-complete-series', 'Garfield & Friends', 'Stories about Garfield the cat, Odie the dog, their owner Jon, and the trouble they get into.', '1988', '1995', 'tt0094469', 35, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(37, 1, 'cartoons', 'active', 'watchoutforthattree', 'George of the Jungle', 'An anthology of Jay Ward cartoon creations, featuring a dumb ape man and his friends.', '1967', '1970', 'tt0061256', 36, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(38, 1, 'cartoons', 'active', 'inspector-gadget-go-go-gadget-series', 'Inspector Gadget', 'A bumbling cyborg inspector outfitted with an array of bizarre gadgets pursues the criminal organization M.A.D., all while his precocious niece and dog do the real investigative work.', '1983', '1986', 'tt0085033', 37, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(39, 1, 'cartoons', 'active', 'jackie-chan-adventures-1080p-ai-upscale', 'Jackie Chan Adventures', 'Jackie Chan teams up with his 11-year-old niece, Jade, traveling the globe to locate a dozen magical talismans before the sinister Dark Hand does.', '2000', '2005', 'tt0259141', 38, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(40, 1, 'cartoons', 'active', 'my-little-pony-80s', 'My Little Pony', 'The story of a human named Megan in a world of magical ponies and their adventures together in Pony Land.', '1986', '1987', 'tt0184761', 39, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(41, 1, 'cartoons', 'active', 'RockyBullwinkleFriends', 'Rocky & Bullwinkle', 'Rocky, a plucky flying squirrel and Bullwinkle, a bumbling but lovable moose, have a series of ongoing adventures.', '1959', '1963', 'tt0052507', 40, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(42, 1, 'cartoons', 'active', 'the-scooby-doo-show', 'Scooby-Doo Show', 'Join Scooby-Doo and the gang in their various adventures in this compilation series.', '1976', '1979', 'tt21153032', 41, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(43, 1, 'cartoons', 'active', 'snorks-complete-series', 'Snorks', 'The Snorks are playful, multicolored underwater creatures that use their built-in snorkels to dart about and make music.', '1984', '1988', 'tt0086802', 42, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(44, 1, 'cartoons', 'active', 's-01-e-04-the-weather-maker', 'Super Friends', 'The greatest of the DC Comics superheroes work together to uphold the good with the help of some young proteges.', '1973', '1985', 'tt0069641', 43, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(45, 1, 'cartoons', 'active', 'tmnt-2003_202404', 'Teenage Mutant Ninja Turtles', 'Follows the adventures of the Teenage Mutant Ninja Turtles, with this iteration being based on the stories from the original mirage comic books.', '2003', '2010', 'tt0318913', 44, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(46, 1, 'cartoons', 'active', 'the.-jetsons', 'The Jetsons', 'The misadventures of a futuristic family.', '1962', '1963', 'tt0055683', 45, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(47, 1, 'cartoons', 'active', 'the-looney-tunes-show_202405', 'The Looney Tunes Show', 'An updated iteration of the classic Looney Tunes characters focusing on their satirical misadventures living in suburbia.', '2011', '2013', 'tt1726839', 46, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(48, 1, 'cartoons', 'active', 'the-powerpuff-girls_fullseries', 'The Powerpuff Girls', 'Three super-powered little girls save the world (or at least the city of Townsville) from monsters and would-be conquerers.', '1998', '2004', 'tt0175058', 47, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(49, 1, 'cartoons', 'active', 'smurfs-1981-complete-series-nbc', 'The Smurfs', 'The Smurfs are tiny blue creatures that live in mushroom houses in a peaceful forest. They repeatedly try to outwit Gargamel, an evil sorcerer, his apprentice, Scruple, and his mangy cat, Azrael.', '1981', '1990', 'tt0081933', 48, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(50, 1, 'cartoons', 'active', 'ep-01_20231021', 'The Yogi Bear Show', 'Yogi, a smooth, talkative forest bear looks to raid park goers\' picnic baskets, while Park Ranger Smith tries to stop him.', '1961', '1962', 'tt0255768', 49, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(51, 1, 'cartoons', 'active', 'tomandjerry-completemgmcollection_202312', 'Tom and Jerry', 'Tom and Jerry is an American animated media franchise and series of comedy short films created in 1940 by William Hanna and Joseph Barbera and feature a cat named Tom and a mouse named Jerry.', '1940', '1967', 'tt28335303', 50, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(52, 1, 'cartoons', 'active', '02.-the-maharajah-of-pookajee_202306', 'Top Cat', 'Top Cat is the leader of a group of alley cats, always trying to cheat someone.', '1961', '1962', 'tt0054572', 51, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(53, 1, 'cartoons', 'active', 'wacky-races-s-01-e-03-why-oh-why-wyoming-sdtv', 'Wacky Races', 'The participants of an unusual car race compete around America.', '1968', '1969', 'tt0122365', 52, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(54, 1, 'cartoons', 'active', 'walter-lantzs-ww-cartoons', 'Woody Woodpecker', 'Woody Woodpecker is a cartoon character that appeared in theatrical short films produced by the Walter Lantz Studio. They were first broadcast on television in 1957 under the title The Woody Woodpecker Show.', '1957', '1972', 'tt0184175', 53, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(55, 1, 'cartoons', 'active', '07.-the-lost-planet-of-atlantis', 'Yogi\'s Space Race', 'Yogi Bear and his friends enter a race to different galaxies in space, but must battle a variety of space creatures out to see that they don\'t finish the race.', '1978', '1978', 'tt0177468', 54, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(56, 1, 'cartoons', 'active', 's-03-e-09-secret-agent-bear', 'Yogi\'s Treasure Hunt', 'Yogi and the gang go on treasure hunts all around the world, as assigned by Top Cat. They travel on board their ship, the S.S. Jelly Roger. Dick Dastardly and Muttley follow them with their ship, the S.S. Dirty Tricks, and try to beat Yogi and his friends', '1985', '1988', 'tt0361260', 55, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(57, 1, 'comedy', 'active', 'most-extreme-elimination-challenge-mxc-112-adult-entertainment-vs-home-improveme', 'MXC Season 1', 'A silly Japanese game show on which contestants are painfully eliminated through barely possible stunts and events, most taking place above pools of mud. Season 1.', '2003', '2007', 'tt0364843', 56, NULL, '2026-07-03 15:51:08', '2026-07-03 15:51:08'),
(58, 1, 'comedy', 'active', 'most-extreme-elimination-challenge-mxc-2-complete', 'MXC Season 2', 'A silly Japanese game show on which contestants are painfully eliminated through barely possible stunts and events, most taking place above pools of mud. Season 2.', '2003', '2007', 'tt0364843', 57, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(59, 1, 'comedy', 'active', 'most-extreme-elimination-challenge-mxc-3-complete', 'MXC Season 3', 'A silly Japanese game show on which contestants are painfully eliminated through barely possible stunts and events, most taking place above pools of mud. Season 3.', '2003', '2007', 'tt0364843', 58, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(60, 1, 'comedy', 'active', 'most-extreme-elimination-challenge-mxc-4-incomplete', 'MXC Season 4', 'A silly Japanese game show on which contestants are painfully eliminated through barely possible stunts and events, most taking place above pools of mud. Season 4.', '2003', '2007', 'tt0364843', 59, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(61, 1, 'comedy', 'active', 'most-extreme-elimination-challenge-mxc-5-incomplete', 'MXC Season 5', 'A silly Japanese game show on which contestants are painfully eliminated through barely possible stunts and events, most taking place above pools of mud. Season 5.', '2003', '2007', 'tt0364843', 60, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(62, 1, 'comedy', 'active', 'abbott_costello_the_vacation', 'Abbott & Costello', 'The Abbott and Costello Show features Bud Abbott and Lou Costello as out-of-work actors and roommates staying at Mr. Field\'s Hollywood boarding house.', '1952', '1957', 'tt0044229', 61, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(63, 1, 'comedy', 'active', 'fgtd5r', 'Faerie Tale Theatre', 'Anthology series starring just about every star that you can think of from the 1980s. Every episode is a different fairy tale ranging from the well known and innocuous to the dark, scary and obscure.', '1982', '1987', 'tt0199214', 62, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(64, 1, 'comedy', 'active', 'get-smart', 'Get Smart', 'Maxwell Smart, a highly intellectual but bumbling spy working for the CONTROL agency, battles the evil forces of rival spy agency KAOS with the help of his competent partner Agent 99.', '1965', '1970', 'tt0058805', 63, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(65, 1, 'comedy', 'active', 'its_always_sunny_complete_archive', 'It\'s Always Sunny in Philadelphia', 'Five friends with big egos and small brains are the proprietors of an Irish pub in Philadelphia.', '2005', '2025', 'tt0472954', 64, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(66, 1, 'comedy', 'active', 'swiss-miss-1938', 'Laurel & Hardy', 'Laurel and Hardy were a British-American comedy team during the early Classical Hollywood era of American cinema, consisting of Englishman Stan Laurel and American Oliver Hardy.', '1927', '1955', 'tt0196372', 65, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(67, 1, 'comedy', 'active', 'madtv-s-01-e-04/MADtv+-+S08E05+-+Episode+%23805.mkv', 'Mad TV', 'A sketch comedy show based on the seminal Mad Magazine.', '1995', '2016', 'tt0112056', 66, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(68, 1, 'comedy', 'active', 'the-super-dave-osborne-show', 'Super Dave', 'Battered and abused stuntman Super Dave Osborne gets his own nighttime talk show.', '1987', '1999', 'tt0092456', 67, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(69, 1, 'comedy', 'active', 'the-lucy-show-lucy-buys-a-boat', 'The Lucy Show', 'The wacky misadventures of a forever-scheming woman (Lucille Ball), her reluctant best friend, and her cantankerous boss.', '1962', '1968', 'tt0055686', 68, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(70, 1, 'comedy', 'active', 'the-monkees-tv-series-full-episodes', 'The Monkees', 'The misadventures of a struggling rock group.', '1965', '1968', 'tt0060010', 69, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(71, 1, 'comedy', 'active', 'the-red-green-show-s-11-m-01-duct-tape-forever-2002-360p-re-dvdrip_202306', 'The Red Green Show', 'Red Green airs his handyman show from Possum Lodge, Canada, and experiences some zany adventures in real life.', '1991', '2006', 'tt0101177', 70, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(72, 1, 'comedy', 'active', 'the-tonight-show-starring-johnny-carson', 'The Tonight Show', 'Host Johnny Carson performs comedy routines and chats with various celebrities.', '1962', '1992', 'tt0055708', 71, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(73, 1, 'comedy', 'active', 'WhitestKidsUKnowSeries', 'The Whitest Kids U Know', 'A sketch comedy show involving five friends.', '2007', '2011', 'tt0840979', 72, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(74, 1, 'documentary', 'active', 'mayday-aci', 'Air Crash Investigation', 'Dramatized reconstruction of real-life air disasters, along with interviews with aviation experts and eyewitnesses. Also known as \"Mayday\".', '2003', '2025', 'tt0386950', 73, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(75, 1, 'documentary', 'active', 'benjamin-franklin-documentary', 'Ben Franklin (History Channel)', 'Ben Franklin rose from poverty to become a true Renaissance Man and Founding Father of America. This is the story of the man behind the myth.', '2004', '2004', 'tt0437862', 74, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(76, 1, 'documentary', 'active', 'BobRossTheHappyPainter', 'Bob Ross: the Happy Painter', 'The start of career, rise to fame, and death of the legendary TV painter Bob Ross.', '2011', '2011', 'tt2155259', 75, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(77, 1, 'documentary', 'active', 'cosmos_202209', 'Carl Sagan: Cosmos', 'Astronomer Carl Sagan leads us on an engaging guided tour of the various elements and cosmological theories of the universe.', '1980', '1980', 'tt0081846', 76, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(78, 1, 'documentary', 'active', 'bbcdavidattenborough', 'David Attenborough', 'A variety of BBC nature documentaries presented by Sir David Attenborough.', '1995', '2009', 'tt0123360', 77, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(79, 1, 'documentary', 'active', 'DinosaurPlanetDiscoveryChannel', 'Dinosaur Planet', 'Four dinosaurs - a female Velociraptor in Asia, Daspletosaurus male in North America, South American female Saltasaurus, and European Pyroraptor - roam their habitats. Narrated by Christian Slater, hosted by paleontologist Scott Sampson.', '2003', '2003', 'tt0389605', 78, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(80, 1, 'documentary', 'active', 'forensic-files-collection', 'Forensic Files', 'A series featuring detailed accounts on how notable crimes and diseases were solved through forensic science.', '1996', '2011', 'tt0247882', 79, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(81, 1, 'documentary', 'active', 'BBCHiroshima', 'Hiroshima (BBC)', 'Documentary with dramatic reenactments with actors to describe what dropping the bomb on Hiroshima was like.', '2005', '2005', 'tt0475296', 80, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(82, 1, 'documentary', 'active', 'ken.-burns.-the.-civil.-war.', 'Ken Burns: Civil War', 'American television documentary miniseries created by Ken Burns about the American Civil War.', '1990', '1990', 'tt0098769', 81, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(83, 1, 'documentary', 'active', 'pbsnovadocs', 'Nova Documentaries', 'Science documentaries about various topics which originally aired on PBS.', '1974', '2024', 'tt0206501', 82, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(84, 1, 'documentary', 'active', 'the-great-war-1964', 'The Great War', 'One of the greatest achievements of television - aired in 26 episodes from 1964. Use of extensive archival material and sound effects combined with contemporary classical music from this era.', '1964', '1964', 'tt0057753', 83, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(85, 1, 'documentary', 'active', 'TheVelvetClawEp3Of7StrengthInNumbers_201901', 'The Velvet Claw', 'From big cats to tiny weasels, this series follows the sometimes surprising evolution of the Carnivora - the mammals that eat meat.', '1992', '1992', 'tt1245441', 84, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(86, 1, 'documentary', 'active', 'the-world-at-war-1973', 'The World At War', 'A groundbreaking 26-part documentary series about the deadliest conflict in history, World War II.', '1973', '1973', 'tt0071075', 85, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(87, 1, 'documentary', 'active', 'lost-civilizations', 'Time Life\'s Lost Civilizations', 'The secrets of lives once lived. Never before could you get so close to 7000 years of history!', '1995', '1995', 'tt0112054', 86, NULL, '2026-07-03 15:51:09', '2026-07-03 15:51:09'),
(88, 1, 'documentary', 'active', 'walkingwithdinosaurs1999miniseries', 'Walking With Dinosaurs', 'Documentary-style series about the era of the dinosaurs, mixing real locations and CGI.', '1999', '1999', 'tt0214382', 87, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(89, 1, 'documentary', 'active', 'Mutual.of.Omahas.Wild.Kingdom', 'Wild Kingdom', 'In Mutual of Omaha\'s Wild Kingdom, host Marlin Perkins explores various animals in their natural habitats.', '1963', '1988', 'tt0121949', 88, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(90, 1, 'drama', 'active', 'doogie-howser-m.d.-season-1-of-4-xvid-avi', 'Doogie Howser, M.D. Season 1', 'A teenage genius deals with the usual problems of growing up, on top of being a licensed physician in a difficult residency program.', '1989', '1993', 'tt0096569', 89, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(91, 1, 'drama', 'active', 'doogie-howser-m.d.-season-2-of-4-xvid-avi', 'Doogie Howser, M.D. Season 2', 'A teenage genius deals with the usual problems of growing up, on top of being a licensed physician in a difficult residency program.', '1989', '1993', 'tt0096569', 90, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(92, 1, 'drama', 'active', 'doogie-howser-m.d.-season-3-of-4-xvid-avi', 'Doogie Howser, M.D. Season 3', 'A teenage genius deals with the usual problems of growing up, on top of being a licensed physician in a difficult residency program.', '1989', '1993', 'tt0096569', 91, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(93, 1, 'drama', 'active', 'doogie-howser-m.d.-season-4-of-4-xvid-avi', 'Doogie Howser, M.D. Season 4', 'A teenage genius deals with the usual problems of growing up, on top of being a licensed physician in a difficult residency program.', '1989', '1993', 'tt0096569', 92, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(94, 1, 'drama', 'active', 'jake.-and.-the.-fatman.-s-01', 'Jake and the Fatman Season 1', 'Veteran district attorney \"Fatman\" McCabe solves cases with the help of his easygoing private investigator partner Jake Styles.', '1987', '1992', 'tt0092381', 93, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(95, 1, 'drama', 'active', 'jake-and-the-fatman-s02', 'Jake and the Fatman Season 2', 'Veteran district attorney \"Fatman\" McCabe solves cases with the help of his easygoing private investigator partner Jake Styles.', '1987', '1992', 'tt0092381', 94, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(96, 1, 'drama', 'active', 'jake-and-the-fatman-s03', 'Jake and the Fatman Season 3', 'Veteran district attorney \"Fatman\" McCabe solves cases with the help of his easygoing private investigator partner Jake Styles.', '1987', '1992', 'tt0092381', 95, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(97, 1, 'drama', 'active', 'jake-and-the-fatman-s04', 'Jake and the Fatman Season 4', 'Veteran district attorney \"Fatman\" McCabe solves cases with the help of his easygoing private investigator partner Jake Styles.', '1987', '1992', 'tt0092381', 96, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(98, 1, 'drama', 'active', 'jake-and-the-fatman-s05', 'Jake and the Fatman Season 5', 'Veteran district attorney \"Fatman\" McCabe solves cases with the help of his easygoing private investigator partner Jake Styles.', '1987', '1992', 'tt0092381', 97, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(99, 1, 'drama', 'active', 'millennium-season-one-1996', 'Millennium Season 1', 'A former FBI profiler with the ability to look inside the mind of a killer begins working for the mysterious Millennium Group which investigates serial killers, conspiracies, the occult, and those obsessed with the end of the millennium. Season 1.', '1996', '1999', 'tt0115270', 98, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(100, 1, 'drama', 'active', '22-the-fourth-horseman-millennium-season-2-1996-1999', 'Millennium Season 2', 'A former FBI profiler with the ability to look inside the mind of a killer begins working for the mysterious Millennium Group which investigates serial killers, conspiracies, the occult, and those obsessed with the end of the millennium. Season 2.', '1996', '1999', 'tt0115270', 99, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(101, 1, 'drama', 'active', 's03-millennium-season-3-1996-1999', 'Millennium Season 3', 'A former FBI profiler with the ability to look inside the mind of a killer begins working for the mysterious Millennium Group which investigates serial killers, conspiracies, the occult, and those obsessed with the end of the millennium. Season 3.', '1996', '1999', 'tt0115270', 100, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(102, 1, 'drama', 'active', 'the-man-from-uncle-s-1', 'Man from UNCLE Season 1', 'The two top Agents of the United Network Command for Law and Enforcement (U.N.C.L.E.) fight the enemies of peace, particularly the forces of T.H.R.U.S.H. Season 1.', '1964', '1968', 'tt0057765', 101, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(103, 1, 'drama', 'active', 'the-man-from-uncle-s-2', 'Man from UNCLE Season 2', 'The two top Agents of the United Network Command for Law and Enforcement (U.N.C.L.E.) fight the enemies of peace, particularly the forces of T.H.R.U.S.H. Season 2.', '1964', '1968', 'tt0057765', 102, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(104, 1, 'drama', 'active', 'the-man-from-uncle-s-3', 'Man from UNCLE Season 3', 'The two top Agents of the United Network Command for Law and Enforcement (U.N.C.L.E.) fight the enemies of peace, particularly the forces of T.H.R.U.S.H. Season 3.', '1964', '1968', 'tt0057765', 103, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(105, 1, 'drama', 'active', 'the-man-from-uncle-s-4', 'Man from UNCLE Season 4', 'The two top Agents of the United Network Command for Law and Enforcement (U.N.C.L.E.) fight the enemies of peace, particularly the forces of T.H.R.U.S.H. Season 4.', '1964', '1968', 'tt0057765', 104, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(106, 1, 'drama', 'active', 'adam-12.-s-01', 'Adam 12 Season 1', 'Two regular police officers patrol Los Angeles.', '1968', '1975', 'tt0062539', 105, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(107, 1, 'drama', 'active', 'baywatch_202212', 'Baywatch', 'At a Los Angeles beach, a team of lifeguards led by Lieutenant Mitch Buchannon save lives and participate in over the top adventures.', '1989', '2001', 'tt0096542', 106, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(108, 1, 'drama', 'active', 'clueless-1996-99', 'Clueless', 'A follow-up to the blockbuster movie of the same name, following the rich teenager Cher and her friends as they attend high school in Beverly Hills.', '1996', '1999', 'tt0115137', 107, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(109, 1, 'drama', 'active', 'Dragnet1951', 'Dragnet (1951)', 'Follows Sergeant Joe Friday of the Los Angels Police Department (LAPD) and his various partners as they methodically investigate a different verity of crimes in Los Angeles, California.', '1951', '1959', 'tt0043194', 108, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(110, 1, 'drama', 'active', 'due-south-1994-99', 'Due South', 'The adventures of an impossibly upright Royal Canadian Mounted Police constable and his American colleagues in the city of Chicago.', '1994', '1999', 'tt0108756', 109, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(111, 1, 'drama', 'active', 'roots-1977-complete-original', 'Roots (miniseries)', 'A dramatization of author Alex Haley\'s family line from ancestor Kunta Kinte\'s enslavement to his descendants\' liberation.', '1977', '1977', 'tt0075572', 110, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(112, 1, 'drama', 'active', 'six-feet-under_202209', 'Six Feet Under', 'When death is your business, what is your life? Laced with irony and dark situational humor, the show approaches the subject of death through the eyes of the Fisher family, who owns and operates a funeral home in Los Angeles.', '2001', '2005', 'tt0248654', 111, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(113, 1, 'drama', 'active', 'st.-elsewhere', 'St. Elsewhere', 'The lives and work of the staff of St. Eligius Hospital, an old and disrespected Boston teaching hospital.', '1982', '1988', 'tt0083483', 112, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(114, 1, 'drama', 'active', 'The_Fugitive_Series', 'The Fugitive', 'A doctor, wrongly convicted for murder, escapes custody and must stay ahead of the police to find the real killer.', '1963', '1967', 'tt0056757', 113, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(115, 1, 'drama', 'active', 'GreenHornetTV', 'The Green Hornet', 'A newspaper publisher and his Asian valet/martial arts expert battle crime as the feared Green Hornet and Kato.', '1966', '1967', 'tt0059991', 114, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(116, 1, 'drama', 'active', 'the-master-complete-series-1984', 'The Master', 'An aging American ninja master and his headstrong young apprentice search for the elder man\'s daughter.', '1984', '1984', 'tt0086756', 115, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(117, 1, 'drama', 'active', 'the-net-complete-tv-series', 'The Net', 'Computer programmer Angela Bennett discovers a shadowy group of cyber terrorists who completely erase her true identity. Falsely labeled a criminal, she finds herself on the run, and she\'ll never stop until she\'s got her life back.', '1998', '1999', 'tt0163953', 116, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(118, 1, 'drama', 'active', '1971-the-homecoming-a-christmas-story', 'The Waltons', 'The life and trials of a 1930s and 1940s Virginia mountain family through financial depression and World War II.', '1972', '1981', 'tt0068149', 117, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(119, 1, 'drama', 'active', 'v-the-original-mini-series-pt-1', 'V (miniseries)', 'A seemingly peaceful alien race, arrives at earth and asks for help to ensure their own planets survival. However, the visitors agenda turns out be much darker.', '1983', '1983', 'tt0085106', 118, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(120, 1, 'drama', 'active', 'v-the-series-1984-85-s-01e-01-liberation-day-hevc', 'V (TV series)', 'A year after Liberation Day, courtesy of the red-dust bacteria, the humanoid, lizard-like aliens develop a resistance to the micro-organism and try to regain control of the Earth--only now, some humans are knowingly working with them.', '1984', '1985', 'tt0086822', 119, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(121, 1, 'drama', 'active', 'walker-texas-ranger-complete-series', 'Walker, Texas Ranger', 'Cordell Walker and his partner, James Trivette, are Texas Rangers who battle crime in Dallas, Texas.', '1993', '2001', 'tt0106168', 120, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(122, 1, 'horror', 'active', 'tales-from-the-darkside-s01-en', 'Tales from the Darkside 1', '\"Tales from the Darkside\" was a horror anthology series where the viewer is taken through ghost stories, science fiction adventures, and creepy, unexplained events. Season 1.', '1983', '1988', 'tt0086814', 121, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(123, 1, 'horror', 'active', 'tales-from-the-darkside-s02-en', 'Tales from the Darkside 2', '\"Tales from the Darkside\" was a horror anthology series where the viewer is taken through ghost stories, science fiction adventures, and creepy, unexplained events. Season 2.', '1983', '1988', 'tt0086814', 122, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(124, 1, 'horror', 'active', 'american-gothic-1995-Complete-tv-series', 'American Gothic', 'A quiet, seemingly-quaint small town is ruled over by its charming yet evil sheriff who uses his demonic powers to remove anyone who dares to stand in his way. The only one he fears is a young boy he fathered through rape.', '1995', '1998', 'tt0111880', 123, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(125, 1, 'horror', 'active', 'bone-chillers-complete-series', 'Bone Chillers', 'The spooky, sometimes even funny, misadventures of a gang of schoolchildren who encounter any number of strange happenings in their school and hometown.', '1996', '1996', 'tt0115114', 124, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(126, 1, 'horror', 'active', 'ghost-story-circle-of-fear-complete', 'Circle of Fear', 'An anthology of suspense dramas concentrating on individuals confronted with supernatural occurrences. Original title: Ghost Story.', '1972', '1973', 'tt0068074', 125, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(127, 1, 'horror', 'active', 'dark-shadows_202210', 'Dark Shadows', 'The rich Collins family of Collinsport, Maine is tormented by strange occurrences.', '1966', '1971', 'tt0059978', 126, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(128, 1, 'horror', 'active', 'freddys-nightmares-complete-tv-series', 'Freddy\'s Nightmares', '\"Freddy\'s Nightmares\" was a 1988 horror anthology series with \"Freddy\", the dream serial killer, hosting stories set in Springwood, USA.', '1988', '1990', 'tt0094466', 127, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(129, 1, 'horror', 'active', 'kolchak-the-night-stalker-complete-series-1972', 'Kolchak: the Night Stalker', 'Carl Kolchak is a reporter for a Chicago newspaper. Through more accident than design he ends up investigating homicides, many of which involve supernatural forces. Ultimately, rather than reporting on the crimes, he solves them.', '1974', '1975', 'tt0071003', 128, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(130, 1, 'horror', 'active', 'kolchak-the-night-strangler-1973', 'Kolchak: The Night Strangler', 'A reporter hunts down a 144-year old alchemist who is killing women for their blood.', '1973', '1973', 'tt0069002', 129, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(131, 1, 'horror', 'active', 's-01-e-01-1.-incident-on-and-off-a-mountain-road', 'Masters of Horror', 'Genre veteran Mick Garris has amassed some of the greatest horror film writers and directors to bring to you the anthology series.', '2005', '2007', 'tt0448190', 130, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(132, 1, 'horror', 'active', 'night-visions-complete-series', 'Night Visions', '\"Night Visions\" was a hosted anthology series similar to \"The Twilight Zone\" - some tales are supernatural, others are just commentaries on twisted human nature. Each hour show is made up of two half-hour stories aired back-to-back.', '2001', '2002', 'tt0247120', 131, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(133, 1, 'horror', 'active', 'rl-stines-the-haunting-hour-full-series', 'The Haunting Hour', 'R.L. Stine leads young viewers on a creepy tour of tales featuring life-sized dolls, werewolves, and carnival clowns that are stalking children. Original title: R.L. Stine\'s The Haunting Hour.', '2010', '2014', 'tt1765510', 132, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(134, 1, 'horror', 'active', 'the_unknown_2012', 'The Unknown', 'Anthology about a man with a blog that searches for the truth behind supernatural phenomena and documents people\'s stories of their experiences with the unknown.', '2012', '2012', 'tt2384747', 133, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(135, 1, 'horror', 'active', 'theatre-macabre-s-1e-4-mateo-falcone-1971', 'Theatre Macabre', '\"Theatre Macabre\" was a horror movie anthology series hosted by Christopher Lee; made in Poland; with stories from various classic authors.', '1971', '1972', 'tt1957799', 134, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(136, 1, 'horror', 'active', 'wolf-lake-2001-complete', 'Wolf Lake', 'Set in the Pacific Northwest, this suspense thriller explores what happens when werewolves overtake a small Seattle suburb.', '2001', '2002', 'tt0281524', 135, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(137, 1, 'kids', 'active', 'arthur-1996-complete_202203', 'Arthur', 'Based on the books by Marc Brown, these are the adventures of Arthur, an 8-year-old aardvark, and his family and friends as they grow up and learn how to be good neighbors to one another.', '1996', '2006', 'tt0169414', 136, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(138, 1, 'kids', 'active', 'barney-friends', 'Barney & Friends', 'Barney, America\'s favorite purple dinosaur, and his young friends share adventures featuring songs, dances and games.', '1992', '2010', 'tt0144701', 137, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(139, 1, 'kids', 'active', 'blues-clues-s-03-e-59-blues-big-musical-movie', 'Blue\'s Clues', 'Blue is a puppy who puts her paw prints on three clues. Steve or Joe has to deduce the clues (with the help of off-screen children) to figure out what Blue wants to do.', '1996', '2007', 'tt0163929', 138, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(140, 1, 'kids', 'active', 'bob-the-builder-classic-full', 'Bob The Builder', 'Bob Builder and his machine team are ready to tackle any project.', '1997', '2018', 'tt0262151', 139, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(141, 1, 'kids', 'active', 'clifford-the-big-red-dog-s-01e-20-clifford-on-parade-follow-the-leader', 'Clifford the Big Red Dog', 'The adventures of a larger-than-life red dog on Bridwell Island.', '2000', '2003', 'tt0233041', 140, NULL, '2026-07-03 15:51:10', '2026-07-03 15:51:10'),
(142, 1, 'kids', 'active', 'dink-the-little-dinosaur-complete-series', 'Dink The Little Dinosaur', 'A small Apatosaurus and his friends learn important life lessons through adventures.', '1989', '1991', 'tt0213340', 141, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(143, 1, 'kids', 'active', 'dinosaur-train-season-1-s-01-e-01-elmer-elasmosaurus', 'Dinosaur Train', 'Friendly dinosaurs climb aboard a train to visit different times throughout the prehistoric age.', '2009', '2023', 'tt1460205', 142, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(144, 1, 'kids', 'active', 'fraggle-toon', 'Fraggle Rock (cartoon)', 'An animated version of Jim Henson\'s classic series.', '1987', '1988', 'tt0273345', 143, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(145, 1, 'kids', 'active', 'goosebumps-s-02-e-20-and-e-21-welcome-to-dead-house', 'Goosebumps', 'A series of scary anthology stories based on the children\'s books by R.L. Stine.', '1995', '1998', 'tt0111987', 144, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(146, 1, 'kids', 'active', 'h_r_pufnstuf', 'H.R. Pufnstuf', 'The adventures of a boy named Jimmy trapped in a fantastic land with a dragon friend, a magic flute, and a witch enemy.', '1969', '1970', 'tt0063907', 145, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(147, 1, 'kids', 'active', 'lazy-town-s-01-e-03-featuring-sports-day', 'LazyTown', 'A pink-haired girl named Stephanie moves to LazyTown and tries to teach its lazy residents about physical activity.', '2002', '2014', 'tt0396991', 146, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(148, 1, 'kids', 'active', 'little-einsteins-dvd-collection-episodes_202409', 'Little Einsteins', 'Four friends go on missions with their ever changing rocket ship. Every mission includes a classic song and a painting.', '2005', '2010', 'tt0756522', 147, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(149, 1, 'kids', 'active', 'martha-speaks-season-1-s-01-e-08-what-s-bothering-bob-martha-spins-a-tale', 'Martha Speaks', 'Tales of a dog, Martha, who eats alphabet soup and gains the ability to speak. This series follows the antics of Martha and her owner as she encounters many adventures with her newfound ability.', '2008', '2016', 'tt1043776', 148, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(150, 1, 'kids', 'active', 'molly-of-denali-s-01-e-03-berry-itchy-day-herring-eggs-or-bust', 'Molly of Denali', 'An action-adventure comedy that follows the adventures of feisty and resourceful 10-year-old Molly Mabray, an Alaska Native girl, her dog Suki, and friends Tooey and Trini on their adventures in epically beautiful Alaska.', '2019', '2024', 'tt8651594', 149, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(151, 1, 'kids', 'active', 'peewee-playhouse', 'Pee Wee\'s Playhouse', 'Pee-Wee Herman and his friends have wacky, imaginative fun in his unique playhouse.', '1986', '1991', 'tt0090500', 150, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(152, 1, 'kids', 'active', 'phil-of-the-future', 'Phil of the Future', 'A family from 2121 is stuck in 2004, trying desperately to fit in.', '2004', '2006', 'tt0340281', 151, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(153, 1, 'kids', 'active', 'ReadingRainbowTVSeries', 'Reading Rainbow', 'Levar Burton introduces young viewers to illustrated readings of children\'s literature and explores their related subjects.', '1983', '2006', 'tt0085075', 152, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(154, 1, 'kids', 'active', 'theberenstainbearscompleteseries', 'The Berenstain Bears (2003)', 'Join the fun-loving Berenstain Bears for exciting adventures in Bear Country. Watch as Sister gets her first tooth, Papa and the cubs learn how to cope around the house without Mama, the kids adopt one of Farmer Ben\'s new puppies, and more.', '2003', '2004', 'tt34475386', 153, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(155, 1, 'kids', 'active', 'the-big-comfy-couch_202108', 'The Big Comfy Couch', 'Loonette the clown and her dolly Molly solve everyday problems while residing in the comfort of a large couch.', '1992', '2013', 'tt0136634', 154, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(156, 1, 'kids', 'active', 'the-hoobs-hiding', 'The Hoobs', 'Hubba Hubba sends some of his Hoobs to Earth so he can learn all about humans and their behaviour.', '2001', '2003', 'tt0462108', 155, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(157, 1, 'kids', 'active', 'the-land-before-time-the-complete-tv-series', 'The Land Before Time', 'The further adventures of Littlefoot and his friends learning about the world of dinosaurs.', '2007', '2008', 'tt0473584', 156, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(158, 1, 'kids', 'active', 'welcome-to-pooh-corner-episode-collection', 'Welcome To Pooh Corner', 'Winnie the Pooh\'s first live-action television series. The popular \"Pooh Corner\" consisted of a mix of full-body costumes and radio controlled \'puppetronics\' that kept the mouths and eyes moving.', '1983', '1986', 'tt0277535', 157, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(159, 1, 'kids', 'active', 'wild-kratts-season-1-s-01-e-01-mom-of-a-croc', 'Wild Kratts', 'The Kratt Brothers and their team use their \"Creature Power\" suits to learn about and help various species of animals around the world.', '2011', '2025', 'tt1807859', 158, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11');
INSERT INTO `playlist_shows` (`id`, `playlist_id`, `category`, `status`, `identifier`, `title`, `description`, `start_year`, `end_year`, `imdb`, `sort_order`, `thumbnail_path`, `created_at`, `updated_at`) VALUES
(160, 1, 'kids', 'active', 'wishbone_20230214_20230214_1416', 'Wishbone', 'An intelligent and witty dog imagines himself in the role of characters from classic books and gets involved in similar real-life adventures.', '1995', '1998', 'tt0112225', 159, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(161, 1, 'kids', 'active', 'WWTVSeries20072011', 'WordWorld', 'In WordWorld, words come alive, words save the day, and words become a child\'s best friend. Welcome to WordWorld, the first preschool series where words are truly the stars of the show!', '2007', '2011', 'tt2217594', 160, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(162, 1, 'kids', 'active', 'YCDTOTV1979-90', 'You Can\'t Do That on Television', 'Sketch TV by young amateur actors in true classic Nickelodeon-style. But whatever you do, never admit that you don\'t know (or ask for water).', '1979', '2004', 'tt0078714', 161, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(163, 1, 'mystery', 'active', 'HardyBoysNancyDrew', 'Hardy Boys/Nancy Drew Mysteries', 'The Hardy Boys/Nancy Drew Mysteries is a television series which aired for three seasons on ABC. The series starred Parker Stevenson and Shaun Cassidy as detective brothers Frank and Joe Hardy, respectively, and Pamela Sue Martin as amateur sleuth Nancy Drew.', '1977', '1979', 'tt0075513', 162, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(164, 1, 'mystery', 'active', 'In-Search-Of-complete-series', 'In Search of...', '\"In Search of...\" was a 1976-1982 television series where the host, Leonard Nimoy, investigated mysterious phenomena.', '1976', '1982', 'tt0074007', 163, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(165, 1, 'mystery', 'active', 'the-new-alfred-hitchcock-presents-complete', 'New Alfred Hitchcock Presents', 'Updated remakes of classic stories from Alfred Hitchcock Presents (1955) and The Alfred Hitchcock Hour (1962), originally produced by the Master of Suspense. This version featured remakes of episodes from the original series as well as original stories.', '1985', '1989', 'tt0088476', 164, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(166, 1, 'mystery', 'active', 'Sherlock_Holmes_1954', 'Sherlock Holmes (1954)', 'The adventures of master detective Sherlock Holmes as he and his assistant, Dr. Watson--and, somewhat reluctantly, the bumbling Inspector Lestrade--battle criminals in London.', '1954', '1955', 'tt0046642', 165, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(167, 1, 'mystery', 'active', 'the-magician-complete-series-1973', 'The Magician', 'The cases of a stage magician/escape artist who moonlights as an amateur crimefighter.', '1973', '1974', 'tt0069606', 166, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(168, 1, 'retro', 'active', 'boop-oop-a-doop.-1932.1080p.-bluray.-dts.x-264-gcjm', 'Betty Boop', 'Pioneering cartoon series (from 1930-1939) from Fleischer Studios, Betty Boop was the mirror of the stereotypical flapper, simultaneously looking for a good time and good-at-heart.', '1932', '1938', 'tt16154234', 167, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(169, 1, 'retro', 'active', 'Felix-The-Cat-1919-1936', 'Felix the Cat', 'Felix the Cat is a cartoon character created in 1919 by Otto Messmer and Pat Sullivan during the silent film era. He\'s one of the most recognized cartoon characters in history. Felix was the first recurring animal character in American animation.', '1919', '1936', 'tt0141134', 168, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(170, 1, 'retro', 'active', 'our-gang-collection', 'Our Gang', 'Also known as The Little Rascals or Hal Roach\'s Rascals, Our Gang is an American series of comedy short films chronicling a group of poor neighborhood children and their adventures.', '1927', '1938', 'tt0029358', 169, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(171, 1, 'retro', 'active', 'popeye-the-sailor-the-complete-series', 'Popeye the Sailor', 'Popeye begins his movie career by singing his theme song, demonstrating his strength at a carnival, dancing the hula with Betty Boop, pummeling Bluto, eating his spinach and saving Olive Oyl from certain doom.', '1933', '1957', 'tt0024461', 170, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(172, 1, 'retro', 'active', 'threestooges19341959', 'The Three Stooges', 'Classic comedy shorts featuring the crazy trio.', '1934', '1959', 'tt0850645', 171, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(173, 1, 'sci-fi', 'active', 'farscape-s-01', 'Farscape Season 1', 'Thrown into a distant part of the universe, an Earth astronaut finds himself part of a fugitive alien starship crew.', '1999', '2003', 'tt0187636', 172, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(174, 1, 'sci-fi', 'active', 'farscape-s-02', 'Farscape Season 2', 'Thrown into a distant part of the universe, an Earth astronaut finds himself part of a fugitive alien starship crew.', '1993', '2003', 'tt0187636', 173, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(175, 1, 'sci-fi', 'active', 'farscape-s-03-', 'Farscape Season 3', 'Thrown into a distant part of the universe, an Earth astronaut finds himself part of a fugitive alien starship crew.', '1999', '2003', 'tt0187636', 174, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(176, 1, 'sci-fi', 'active', 'farscape-s-04', 'Farscape Season 4', 'Thrown into a distant part of the universe, an Earth astronaut finds himself part of a fugitive alien starship crew.', '1999', '2003', 'tt0187636', 175, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(177, 1, 'sci-fi', 'active', 'farscape-the-peacekeeper-wars', 'The Peacekeeper Wars', 'Thrown into a distant part of the universe, an Earth astronaut finds himself part of a fugitive alien starship crew.', '1999', '2003', 'tt0187636', 176, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(178, 1, 'sci-fi', 'active', 'JimmyAndTheFederationGang', 'Star Trek Season 1', 'Season 1: Captain James T. Kirk and the crew of the U.S.S. Enterprise explore the galaxy and defend the United Federation of Planets.', '1966', '1969', 'tt0060028', 177, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(179, 1, 'sci-fi', 'active', 'JimmyAndTheFederationGang-2', 'Star Trek Season 2', 'Season 2: Captain James T. Kirk and the crew of the U.S.S. Enterprise explore the galaxy and defend the United Federation of Planets.', '1966', '1969', 'tt0060028', 178, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(180, 1, 'sci-fi', 'active', 'JimmyAndTheFederationGang3', 'Star Trek Season 3', 'Season 3: Captain James T. Kirk and the crew of the U.S.S. Enterprise explore the galaxy and defend the United Federation of Planets.', '1966', '1969', 'tt0060028', 179, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(181, 1, 'sci-fi', 'active', 'the-outer-limits-1x-01-02-the-sandkings', 'The Outer Limits Season 1', 'The Outer Limits (1995) is a science fiction anthology television series which is a revival of the original series that aired from 1963 to 1965. Season 1.', '1995', '2002', 'tt0112111', 180, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(182, 1, 'sci-fi', 'active', 'the-outer-limits-2x-06-beyond-the-veil', 'The Outer Limits Season 2', 'The Outer Limits (1995) is a science fiction anthology television series which is a revival of the original series that aired from 1963 to 1965. Season 2.', '1995', '2002', 'tt0112111', 181, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(183, 1, 'sci-fi', 'active', 'the-outer-limits-3x-01-bits-of-love', 'The Outer Limits Season 3', 'The Outer Limits (1995) is a science fiction anthology television series which is a revival of the original series that aired from 1963 to 1965. Season 3.', '1995', '2002', 'tt0112111', 182, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(184, 1, 'sci-fi', 'active', 'the-outer-limits-4x-11-the-vaccine', 'The Outer Limits Season 4', 'The Outer Limits (1995) is a science fiction anthology television series which is a revival of the original series that aired from 1963 to 1965. Season 4.', '1995', '2002', 'tt0112111', 183, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(185, 1, 'sci-fi', 'active', 'the-outer-limits-5x-13-summit', 'The Outer Limits Season 5', 'The Outer Limits (1995) is a science fiction anthology television series which is a revival of the original series that aired from 1963 to 1965. Season 5.', '1995', '2002', 'tt0112111', 184, NULL, '2026-07-03 15:51:11', '2026-07-03 15:51:11'),
(186, 1, 'sci-fi', 'active', 'the-outer-limits-6x-21-final-appeal-part-1', 'The Outer Limits Season 6', 'The Outer Limits (1995) is a science fiction anthology television series which is a revival of the original series that aired from 1963 to 1965. Season 6.', '1995', '2002', 'tt0112111', 185, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(187, 1, 'sci-fi', 'active', 'the-outer-limits-7x-19-the-tipping-point', 'The Outer Limits Season 7', 'The Outer Limits (1995) is a science fiction anthology television series which is a revival of the original series that aired from 1963 to 1965. Season 7.', '1995', '2002', 'tt0112111', 186, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(188, 1, 'sci-fi', 'active', 'AdventuresOfSupermanS01e02TheHauntedLighthouse_201901', 'Adventures of Superman', 'The Man of Steel fights crime with help from his friends at the \'Daily Planet.\'', '1952', '1958', 'tt0044231', 187, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(189, 1, 'sci-fi', 'active', 's-01-e-01-x-02-x-03-saga-of-the-worlds_202502', 'Battlestar Galactica (1978)', 'After the destruction of the Twelve Colonies of Mankind, the last major fighter carrier leads a makeshift fugitive fleet on a desperate search for the legendary planet Earth.', '1978', '1979', 'tt0076984', 188, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(190, 1, 'sci-fi', 'active', 'beyond-westworld-Complete-Series-1980', 'Beyond Westworld', 'The security chief of an android manufacturing company must stop a mad scientist, who\'s sending the failed theme park\'s androids to infiltrate society for his own ends.', '1980', '1980', 'tt0080198', 189, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(191, 1, 'sci-fi', 'active', 'LogansRunMovie', 'Logan\'s Run (Movie)', 'A police officer in the future uncovers the deadly secret behind a society that worships youth.', '1976', '1976', 'tt0074812', 190, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(192, 1, 'sci-fi', 'active', 'logans-run-complete-series-1977', 'Logan\'s Run (TV series)', 'In a futuristic society where reaching the age of 30 is a death sentence, a rebellious law enforcement agent goes on the run in search of Sanctuary.', '1977', '1978', 'tt0075527', 191, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(193, 1, 'sci-fi', 'active', 'man-from-atlantis-complete-series-1977', 'Man From Atlantis', 'The adventures of an amphibious man, the last survivor of the legendary sunken city.', '1977', '1978', 'tt0075533', 192, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(194, 1, 'sci-fi', 'active', 'max-headroom-complete', 'Max Headroom', 'An intrepid investigative TV reporter does his job with the help of his colleagues and a computerized version of himself.', '1987', '1988', 'tt0092402', 193, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(195, 1, 'sci-fi', 'active', 'the-immortal-complete-series-1970', 'The Immortal', 'An immortal man whose blood can have miraculous health benefits is a fugitive from those who would exploit both him and his brother he seeks.', '1969', '1971', 'tt0065303', 194, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(196, 1, 'sci-fi', 'active', 'the-time-tunnel', 'The Time Tunnel', 'Two scientists with a secret time travel project find themselves trapped in the time stream and appearing in notable periods of history.', '1966', '1967', 'tt0060036', 195, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(197, 1, 'sitcom', 'active', 'mister-ed-s-01', 'Mister Ed Season 1', 'The misadventures of a wisecracking talking horse and his human owner.', '1961', '1966', 'tt0054557', 196, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(198, 1, 'sitcom', 'active', 'mister-ed-s-02-', 'Mister Ed Season 2', 'The misadventures of a wisecracking talking horse and his human owner.', '1961', '1966', 'tt0054557', 197, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(199, 1, 'sitcom', 'active', 'mister-ed-s-03', 'Mister Ed Season 3', 'The misadventures of a wisecracking talking horse and his human owner.', '1961', '1966', 'tt0054557', 198, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(200, 1, 'sitcom', 'active', 'mister-ed-s-04', 'Mister Ed Season 4', 'The misadventures of a wisecracking talking horse and his human owner.', '1961', '1966', 'tt0054557', 199, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(201, 1, 'sitcom', 'active', 'the-addams-family__season-1', 'The Addams Family Season 1', 'The misadventures of a blissfully macabre but extremely loving family.', '1964', '1966', 'tt0057729', 200, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(202, 1, 'sitcom', 'active', 'the-addams-family__season-2', 'The Addams Family Season 2', 'The misadventures of a blissfully macabre but extremely loving family.', '1964', '1966', 'tt0057729', 201, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(203, 1, 'sitcom', 'active', 'ALF-The-Complete-Series', 'ALF', 'When an ugly creature, who loves eating cats, crash-lands into the Tanner family\'s garage, they treat him as a guest and allow him to live with them as he comments on the stupidity of mankind.', '1986', '1990', 'tt0090390', 202, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(204, 1, 'sitcom', 'active', 'benson-complete-tv-series-ctv', 'Benson', 'Sharp-tongued butler, Benson DuBois, is the governor\'s director of household affairs.', '1979', '1986', 'tt0078569', 203, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(205, 1, 'sitcom', 'active', 'bewitchedcomplete_202310', 'Bewitched', 'A witch marries an ordinary mortal man and vows to lead the life of a typical housewife.', '1964', '1972', 'tt0057733', 204, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(206, 1, 'sitcom', 'active', 'diffrent-strokes-s-01-e-19-the-trip_202402', 'Diff\'rent Strokes', 'Different Strokes: The misadventures of suave Park Avenue millionaire Phillip Drummond, his teenage daughter Kimberly, and their current housekeeper Edna Garrett who adopted the two pre-teenage sons of their late African American housekeeper from Harlem.', '1978', '1986', 'tt0077003', 205, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(207, 1, 'sitcom', 'active', 'familymatters_202507', 'Family Matters', 'The Winslow family deals with various misadventures, many of them caused by their pesky next-door neighbor, ultra-nerd Steve Urkel.', '1989', '1998', 'tt0096579', 206, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(208, 1, 'sitcom', 'active', 'friends-1994-2004-full-series_20250419', 'Friends', 'The personal and professional lives of six friends living in the Manhattan borough of New York City.', '1994', '2004', 'tt0108778', 207, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(209, 1, 'sitcom', 'active', 'GreenAcresCompleteSeries', 'Green Acres', 'A New York City attorney and his wife attempt to live as farmers in the bizarre community of Hooterville.', '1965', '1971', 'tt0058808', 208, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(210, 1, 'sitcom', 'active', 'leave-it-to-beaver-the-complete-series-1957-1963', 'Leave It to Beaver', 'The misadventures of a suburban boy, family and friends.', '1957', '1963', 'tt0050032', 209, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(211, 1, 'sitcom', 'active', 'malcolm-in-the-middle-complete-series-2000-2006', 'Malcolm in the Middle', 'A gifted young teen tries to survive life with his dimwitted, dysfunctional family.', '2000', '2006', 'tt0212671', 210, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(212, 1, 'sitcom', 'active', 'married-with-children-woc', 'Married With Children', 'Al Bundy is the quintessential working-class dad while his wife, Peggy, always wants more.', '1987', '1997', 'tt0092400', 211, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(213, 1, 'sitcom', 'active', 'newsradio-1995-99', 'NewsRadio', 'Workplace sitcom explores office politics and interpersonal relationships of the staff at WNYX NewsRadio', '1995', '1999', 'tt0112095', 212, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(214, 1, 'sitcom', 'active', 'Petticoat_Junction_Series', 'Petticoat Junction', 'The misadventures of the family staff of The Shady Rest Hotel and their neighbors of Hooterville.', '1963', '1970', 'tt0056780', 213, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(215, 1, 'sitcom', 'active', 'punky-brewster-s-01-e-15-e-16-yes-punky-there-is-a-santa-claus', 'Punky Brewster', 'Young Punky Brewster is abandoned with her dog, Brandon, in a supermarket. When she befriends Henry Warnimont, her new family life begins.', '1984', '1988', 'tt0086787', 214, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(216, 1, 'sitcom', 'active', 'roseanne_202507', 'Roseanne', 'Stars Roseanne Barr as Roseanne Conner and features her everyday American working-class family in the fictional town of Lanford, Illinois. Originally aired on ABC from 1988 to 1997, and was briefly revived in 2018.', '1988', '2018', 'tt0094540', 215, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(217, 1, 'sitcom', 'active', 'savedbythebell_202507', 'Saved By The Bell', 'A close-knit group of six friends get through their teens together while attending Bayside High School in Palisades, California.', '1989', '1992', 'tt0096694', 216, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(218, 1, 'sitcom', 'active', 'TheBobNewhartShow', 'The Bob Newhart Show', 'The professional and personal misadventures of a psychologist and his family, patients, friends and colleagues.', '1972', '1978', 'tt0068049', 217, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(219, 1, 'sitcom', 'active', 'Ghost-Muir', 'The Ghost & Mrs. Muir', 'A house is haunted by a deceased sea captain who wreaks havoc with the new tenants who were not advised of his existence.', '1968', '1970', 'tt0062565', 218, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(220, 1, 'sports', 'active', 'classicmonsterjam', 'Monster Jam', 'Feld Sports Entertainment presents the biggest and baddest monster truck event in a weekly televised event featuring racing, donuts and freestyle action.', '2003', '2013', 'tt1591949', 219, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(221, 1, 'western', 'active', 'Alias-Smith-And-Jones-1973', 'Alias Smith And Jones', 'Hannibal Heyes and Kid Curry, two of the most wanted outlaws in the history of the West, are popular \"with everyone except the railroads and the banks\".', '1971', '1973', 'tt0066625', 220, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(222, 1, 'western', 'active', 'annieoakleys01', 'Annie Oakley', 'A fictionalized account of the life of legendary Wild West sharpshooter Annie Oakley.', '1954', '1957', 'tt0046578', 221, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(223, 1, 'western', 'active', 'bonanzapd', 'Bonanza', 'The Wild West adventures of Ben Cartwright and his sons as they run and defend their Nevada ranch.', '1959', '1973', 'tt0052451', 222, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(224, 1, 'western', 'active', 's01e16buffalobilljr.graveofthemonsters', 'Buffalo Bill Jr.', 'Buffalo Bill Jr. and his kid sister Calamity are raised under the watchful eye of Judge Ben \'Fair and Square\" Wiley. Together this dynamic trio keep law and order in small town of Wileyville, Arizona.', '1955', '1956', 'tt0132652', 223, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(225, 1, 'western', 'active', 'legend-complete-series-1995', 'Legend (1995)', 'In the waning days of the 19th century, dime novelist Ernest Pratt assumes the persona of his noble literary hero, Nicodemus Legend, and roams the Old West with his scientist friend Dr. Janos Bartok.', '1995', '1995', 'tt0112045', 224, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(226, 1, 'western', 'active', 'sky-king-tv-series', 'Sky King', 'Sky King is an American radio and television series. Its lead character was Arizona rancher and aircraft pilot Schuyler \"Sky\" King. The series had strong Western elements.', '1951', '1962', 'tt0043232', 225, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(227, 1, 'western', 'active', 'walt-disneys-texas-john-slaughter-episode-13-end-of-the-trail', 'Texas John Slaughter', 'Texas John Slaughter is a western television series which aired seventeen episodes between October 31, 1958 and April 23, 1961, as part of The Wonderful World of Disney. The character was based upon an actual historical figure, Texas Ranger John Horton Slaughter.', '1958', '1961', 'tt0046593', 226, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(228, 1, 'western', 'active', 'theloneranger_201705', 'The Lone Ranger', 'The adventures of the masked hero and his Native American partner, Tonto.', '1949', '1957', 'tt0041038', 227, NULL, '2026-07-03 15:51:12', '2026-07-03 15:51:12'),
(229, 1, 'western', 'active', 'theroyrogersshow', 'The Roy Rogers Show', 'The Double R Ranch featured Roy Rogers \'The King of the Cowboys\', his \'Smartest Horse in the Movies\' Trigger, \'Queen of the West\' Dale Evans, her horse Buttermilk, their dog Bullet, sidekick Pat Brady, and even Pat\'s jeep, Nellybelle.', '1951', '1957', 'tt0043225', 228, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(230, 2, 'comedy', 'active', 'allo-allo', '\'Allo \'Allo', 'In France during World War II, René Artois runs a small café where Resistance fighters, Gestapo men, German Army officers and escaped Allied POWs interact daily, ignorant of one another\'s true identity or presence, exasperating René.', '1982', '1992', 'tt0086659', 0, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(231, 2, 'comedy', 'active', 'black-books-tv-series-2000-2004', 'Black Books', 'Bernard Black runs a book shop, though his customer service skills leave something to be desired. He hires Manny as an employee. Fran runs the shop next door. Between the three of them many adventures ensue.', '2000', '2004', 'tt0262150', 1, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(232, 2, 'comedy', 'active', 'blackadder-remastered', 'Blackadder', 'In the Middle Ages, Prince Edmund the Black Adder constantly schemes and endeavors to seize the crown from his father and brother.', '1982', '1989', 'tt0084988', 2, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(233, 2, 'comedy', 'active', 'dadsarmythecompletecollection', 'Dad\'s Army', 'A ragtag group of Home Guard volunteers prepare for an imminent German invasion during World War II.', '1968', '1977', 'tt0062552', 3, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(234, 2, 'comedy', 'active', 'father-ted-2005', 'Father Ted', 'Three misfit priests and their housekeeper live on Craggy Island, not the peaceful and quiet part of Ireland that it seems to be.', '1995', '1998', 'tt0111958', 4, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(235, 2, 'comedy', 'active', 'FawltyTowers', 'Fawlty Towers', 'Hotel owner Basil Fawlty\'s incompetence, short fuse, and arrogance form a combination that ensures accidents and trouble are never far away.', '1975', '1979', 'tt0072500', 5, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(236, 2, 'comedy', 'active', 'fs-series', 'French and Saunders', 'Comedy duo Dawn French and Jennifer Saunders satirically present aspects of British life, films such as Batman Forever and Pulp Fiction.', '1987', '2017', 'tt0092355', 6, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(237, 2, 'comedy', 'active', 'keeping-up-appearances_202402', 'Keeping Up Appearances', 'A snobbish housewife is determined to climb the social ladder, in spite of her family\'s working class connections and the constant chagrin of her long suffering husband.', '1990', '1995', 'tt0098837', 7, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(238, 2, 'comedy', 'active', 'lastofthesummerwine', 'Last Of The Summer Wine', 'Three old men from Yorkshire who have never grown up face the trials of their fellow town citizens and everyday life and stay young by reminiscing about the days of their youth and attempting feats not common to the elderly.', '1973', '2010', 'tt0069602', 8, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(239, 2, 'comedy', 'active', 'mpfc-remastered_hd', 'Monty Python\'s Flying Circus', 'The original surreal sketch comedy showcase for the Monty Python troupe.', '1969', '1974', 'tt0063929', 9, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(240, 2, 'comedy', 'active', 'mr-bean-the-complete-live-action-series', 'Mr. Bean', 'Bumbling, childlike Mr. Bean has trouble completing the simplest of day-to-day tasks, but his perseverance and resourcefulness frequently allow him to find ingenious ways around problems.', '1990', '1995', 'tt0096657', 10, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(241, 2, 'comedy', 'active', 'mrs.-browns-boys-s-03-e-11-mammys-christmas-punch', 'Mrs. Brown\'s Boys', 'A comedy centered on a loud-mouthed Irish matriarch whose favorite pastime is meddling in the lives of her six children.', '2011', '2014', 'tt1819022', 11, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(242, 2, 'comedy', 'active', 'bbc-red-dwarf', 'Red Dwarf', 'The adventures of the last human alive and his friends, stranded 3 million years into deep space on the mining ship Red Dwarf.', '1988', '2020', 'tt0094535', 12, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(243, 2, 'comedy', 'active', 'ripping-yarns-1976-79', 'Ripping Yarns', 'This show is a collection of tales that make for \"ripping good\" television. Sir Michael Palin played a different lead character in each yarn.', '1976', '1979', 'tt0075568', 13, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(244, 2, 'comedy', 'active', 'the-benny-hill-show', 'The Benny Hill Show', 'A skit based show with Benny Hill, often containing smutty humour.', '1969', '1989', 'tt0063869', 14, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(245, 2, 'comedy', 'active', 'vicar-of-dibley-s-01-e-01-arrival.avi', 'The Vicar of Dibley', 'A boisterous female minister comes to serve in an eccentrically conservative village\'s church.', '1994', '2000', 'tt0108981', 15, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(246, 2, 'comedy', 'active', 'the-young-ones-s-01-ep-04-bomb', 'The Young Ones', 'The crazy and sometimes surreal comedic adventures of four very different students in Thatcher\'s Britain.', '1982', '1984', 'tt0083505', 16, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(247, 2, 'comedy', 'active', 'yes-minister-1980-1984', 'Yes Minister', 'The Right Honorable James Hacker has landed the plum job of Cabinet Minister to the Department of Administration. At last he is in a position of power and can carry out some long-needed reforms, or so he thinks.', '1980', '1984', 'tt0080306', 17, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(248, 2, 'cooking', 'active', 'two.-fat.-ladies.-s-01-e-04.-cakes.and.-baking.-sdtv.x-265-aac', 'Two Fat Ladies', 'Clarissa and Jennifer are two long-time friends who enjoy driving on their motorcycle and cooking ethnic foods according to where they live.', '1996', '1999', 'tt0169499', 18, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(249, 2, 'Drama', 'active', '01-rumpole-of-the-bailey-s-01-e-01-rumpole-and-the-younger-generation-1', 'Rumpole Of The Bailey', 'The cases of a portly and eccentric criminal law barrister.', '1978', '1992', 'tt0078680', 19, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(250, 2, 'Drama', 'active', 'the-professionals-complete-series-1977', 'The Professionals', 'Bodie and Doyle, senior agents of the British intelligence service CI5 (Criminal Intelligence 5), and their handler George Cowley fight terrorism and similar high-level crimes.', '1977', '1983', 'tt0075561', 20, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(251, 2, 'kids', 'active', 'charlie-chalk-the-complete-series-1988-89', 'Charlie Chalk', 'A clown named Charlie Chalk arrives on the island of Merrytwit after falling asleep on his boat. He befriends the inhabitants of the island and gets into many adventures.', '1988', '1989', 'tt0436999', 21, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(252, 2, 'kids', 'active', 'enchanted-world-of-brambly-hedge-1996-2000', 'Brambly Hedge', 'The enchanting tales of a community of field mice living in secret in a brambly hedgerow. The full name of the show is \"The Enchanted World of Brambly Hedge\".', '1996', '2000', 'tt0796568', 22, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(253, 2, 'kids', 'active', 'little-miss-1983-bbc-tv-series-the-complete-collection', 'Little Miss', 'The adventures of the Little Miss characters created by Roger Hargreaves as female counterparts to the original Mr Men. The personality and appearance of each character matches their name.', '1983', '1983', 'tt0299341', 23, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(254, 2, 'kids', 'active', 'mr.-men-1974-78-bbc-tv-series-the-complete-collection', 'Mr. Men', 'Follows brightly colored characters that live in Misterland. All of them have names like Mr Happy, Mr Clumsy and Mr Greedy and their appearance and personality match their name.', '1974', '1983', 'tt0426754', 24, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(255, 2, 'kids', 'active', 'noddy-goes-to-toyland-1963-arthur-humberstone', 'Noddy Goes To Toyland', 'This charming colour cartoon of Enid Blyton’s timeless toy boy was produced as a pilot episode for an unrealised series. It is a faithful but curtailed version of Blyton’s book of the same name, first published in 1949.', '1963', '1963', 'tt19815358', 25, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(256, 2, 'kids', 'active', 'wombles-1973-77', 'The Wombles', 'The misadventures of a fantasy folk community dedicated to cleaning up litter and putting it to their own use.', '1973', '1975', 'tt0159227', 26, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(257, 2, 'kids', 'active', 'thomas-and-friends-the-complete-series-uk', 'Thomas and Friends', 'This series follows the adventures of Thomas the Tank Engine and all of his engine friends on the Island of Sodor.', '1984', '2025', 'tt0086815', 27, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(258, 2, 'kids', 'active', 'ThunderbirdsSeries', 'Thunderbirds', 'In the year 2065, the Tracy family run International Rescue - a top-secret organization whose ongoing mission is to rescue people trapped in extraordinarily dangerous situations using their advanced Thunderbirds machines.', '1965', '1966', 'tt0057790', 28, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(259, 2, 'kids', 'active', 'world-of-peter-rabbit-and-friends-1992-98', 'World of Peter Rabbit & Friends', 'An animated series, telling the story of many beloved Beatrix Potter characters.', '1992', '1998', 'tt0296886', 29, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(260, 2, 'mystery', 'active', 'midsomer-murders-season1', 'Midsomer Murders Season 1', 'A veteran Detective Chief Inspector and his young Sergeant investigate murders around the regional community of Midsomer County.', '1997', '2025', 'tt0118401', 30, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(261, 2, 'mystery', 'active', 'midsomer-murders-season2', 'Midsomer Murders Season 2', 'A veteran Detective Chief Inspector and his young Sergeant investigate murders around the regional community of Midsomer County.', '1997', '2025', 'tt0118401', 31, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(262, 2, 'mystery', 'active', 'midsomer-murders-season3', 'Midsomer Murders Season 3', 'A veteran Detective Chief Inspector and his young Sergeant investigate murders around the regional community of Midsomer County.', '1997', '2025', 'tt0118401', 32, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(263, 2, 'mystery', 'active', 'SherlockHolmesGranada', 'Adventures of Sherlock Holmes', 'Sherlock Holmes and Dr Watson solve the mysteries of copper beeches, a Greek interpreter, the Norwood builder, a resident patient, the red-headed league, and one final problem.', '1984', '1985', 'tt0086661', 33, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(264, 2, 'mystery', 'active', 'miss-marple-1984-92', 'Miss Marple', 'Miss Marple, an elderly woman and amateur detective whose sharp mind helps her solve a series of seemingly baffling cases.', '1984', '1992', 'tt9907044', 34, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(265, 2, 'mystery', 'active', 'pie-in-the-sky-94-97', 'Pie in the Sky', 'After a botched sting operation, and denied his imminent retirement, DI Crabbe is suspended from the police and opens his own restaurant. However, his ex-boss, Assistant Chief Constable Freddy Fisher, constantly calls him back on duty.', '1994', '1997', 'tt0106102', 35, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(266, 2, 'mystery', 'active', 'poirot-series', 'Poirot', 'Hercule Poirot, a Belgian detective, has an impeccable knack for getting embroiled in a mystery and solving crimes.', '1989', '2013', 'tt0094525', 36, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(267, 2, 'mystery', 'active', 'BBC_Sherlock2010', 'Sherlock (2010)', 'The quirky spin on Conan Doyle\'s iconic sleuth pitches him as a \"high-functioning sociopath\" in modern-day London. Assisting him in his investigations: Afghanistan War vet John Watson, who\'s introduced to Holmes by a mutual acquaintance.', '2010', '2017', 'tt1475582', 37, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(268, 2, 'sci-fi', 'active', 'doctor-who-2005-s01', 'Doctor Who (2005) Series 1', 'Continuing on from Doctor Who (1963), this revival follows the further adventures of the Doctor and their companions as they encounter various alien threats and save civilizations on different planets and time periods.', '2005', '2007', 'tt0436992', 38, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(269, 2, 'sci-fi', 'active', 'season-2-202315', 'Doctor Who (2005) Series 2', 'Continuing on from Doctor Who (1963), this revival follows the further adventures of the Doctor and their companions as they encounter various alien threats and save civilizations on different planets and time periods.', '2005', '2007', 'tt0436992', 39, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(270, 2, 'sci-fi', 'active', 'Space1999.Series1', 'Space 1999 Series 1', 'Follows the crew of Moonbase Alpha who struggle to survive when a massive explosion throws the Moon from Earths orbit and out into deep space.', '1975', '1977', 'tt0072564', 40, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(271, 2, 'sci-fi', 'active', 'Space1999.Series1_201602', 'Space 1999 Series 2', 'Follows the crew of Moonbase Alpha who struggle to survive when a massive explosion throws the Moon from Earths orbit and out into deep space.', '1975', '1977', 'tt0072564', 41, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(272, 2, 'sci-fi', 'active', 'doctor-who_202210', 'Doctor Who (1963)', 'The adventures of \'The Doctor\', a Time Lord who changes appearance and personality by regenerating when near death.', '1963', '1989', 'tt0056751', 42, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(273, 2, 'sci-fi', 'active', 'the-hitchhikers-guide-to-the-galaxy-complete-series-1981', 'Hitchhiker\'s Guide to the Galaxy', 'Arthur Dent and his friend, Ford Prefect, escape the destruction of Earth, only to face incredible trials, tribulations and adventures in space and time.', '1981', '1981', 'tt0081874', 43, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(274, 2, 'sci-fi', 'active', 'UFO.complete', 'UFO', 'In 1980, the Supreme Headquarters Alien Defence Organization covertly defends Earth against threats from a dying extraterrestrial race that needs to harvest human organs to survive.', '1970', '1971', 'tt0063962', 44, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(275, 2, 'Drama', 'active', 'tales-of-the-unexpected-1979-88', 'Tales of the Unexpected', 'Short dramas each with a twist of some kind; across the first four seasons most of these are from short stories by Roald Dahl.', '1979', '1988', 'tt0075592', 45, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(276, 2, 'Drama', 'active', 'The_Prisoner', 'The Prisoner', 'A former secret agent is abducted and taken to what looks like an idyllic village, but is actually a bizarre prison. He refuses to give his warders information while attempting to escape.', '1967', '1968', 'tt0061287', 46, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(277, 2, 'kids', 'active', 'animals-of-farthing-wood-1993-95', 'Animals of Farthing Wood', 'A group of wild animal friends are forced to move to a park after humans drive them away from their old home.', '1993', '1995', 'tt0286336', 47, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(278, 2, 'kids', 'active', 'Stingray.Complete', 'Stingray', 'In 2064, Captain Troy Tempest of the World Aquanaut Security Patrol and his crew explore the oceans in their combat submarine Stingray, encountering both friendly and hostile undersea aliens.', '1964', '1965', 'tt0057786', 48, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(279, 2, 'Drama', 'active', 'the-tomorrow-people-1973-79', 'The Tomorrow People', 'A group of teens with psychic and other paranormal abilities use their special gifts to battle evil.', '1973', '1979', 'tt0069647', 49, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(280, 2, 'mystery', 'active', 'midsomer-murders-season4', 'Midsomer Murders Season 4', 'A veteran Detective Chief Inspector and his young Sergeant investigate murders around the regional community of Midsomer County.', '1997', '2025', 'tt0118401', 50, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(281, 2, 'comedy', 'active', 'it-aint-half-hot-mum-s-06-e-04-the-dhobi-wallahs', 'It Ain\'t Half Hot Mum', 'The comic adventures of a group of misfits who form an extremely bad concert party touring the hot and steamy jungles of Burma entertaining the troops during World War II.', '1974', '1981', 'tt0081878', 51, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(282, 2, 'kids', 'active', 'Supercar.Series1', 'Supercar', 'The adventures of Mike Mercury and the crew at Black Rock Laboratory in the Nevada Desert as they test out Supercar, a vehicle capable of traveling on land, underwater, and in the air.', '1961', '1962', 'tt0054567', 52, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(283, 2, 'kids', 'active', 'noddys-toyland-adventures-1992', 'Noddy\'s Toyland Adventures', 'Young boy Noddy experiences adventures in Toyland, hosting parties for friends. Each episode features him finding a new toy and ends with a character getting \"goo\'ed\" in fun mishaps.', '1992', '2000', 'tt0108881', 53, NULL, '2026-07-03 15:51:13', '2026-07-03 15:51:13'),
(284, 2, 'sci-fi', 'active', 'star-cops-complete-series-1987', 'Star Cops', 'Set in the year 2027 this follows the exploits of the fledgling International Space Police Force, nicknamed the \"Star Cops\".', '1987', '1987', 'tt0088613', 54, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(285, 2, 'Drama', 'active', 'ttss_20240201', 'Tinker, Tailor, Soldier, Spy', 'In the bleak days of the Cold War, espionage veteran George Smiley is forced out of semi-retirement to uncover a Soviet agent within MI6\'s echelons.', '1979', '1979', 'tt0080297', 55, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(286, 2, 'Drama', 'active', 'dempsey-and-makepeace-complete-series-1985', 'Dempsey and Makepeace', 'Dempsey, a tough NYPD cop, is sent to a London undercover police unit teamed up with the sophisticated, sexy, blonde Makepeace. They hunt down the top of London\'s underworld - when not quarreling.', '1985', '1986', 'tt0088503', 56, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(287, 2, 'mystery', 'active', 'father-brown-complete-series-1974', 'Father Brown', 'Based on the stories of G.K. Chesterton, a British Catholic Priest solves mysteries.', '1974', '1974', 'tt0069582', 57, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(288, 2, 'Drama', 'active', 'raffles-complete-series-1975', 'Raffles', 'A.J. Raffles, a gentleman cricketer, leads a double life as a jewel thief, aided by his friend Bunny Manders. He steals from the wealthy while occasionally playing detective across Edwardian England.', '1975', '1977', 'tt0075563', 58, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(289, 2, 'kids', 'active', 'into-the-labyrinth-Complete-series-1980', 'Into the Labyrinth', 'Three children stumble upon the imprisoned sorcerer Rothgo and are drawn into a mysterious search through time for the magical Nidus which has been stolen by the evil witch Belor.', '1981', '1982', 'tt0081877', 59, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(290, 2, 'Drama', 'active', 'the-smuggler-complete-series-1981', 'Smuggler', 'A former British naval officer turned smuggler navigates the espionage war between England and France in 1802, evading revenue officers while entangled in the clashing nations\' covert operations.', '1981', '1981', 'tt0081932', 60, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(291, 2, 'sci-fi', 'active', 'the-invisible-man-1984_202505', 'The Invisible Man', 'A scientist named Griffin invents a way to change his body\'s refractive index and thus becomes invisible. He uses the opportunity to carry out random acts of violence.', '1984', '1984', 'tt0087478', 61, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(292, 2, 'comedy', 'active', 'the-gaffer-complete-series-1981', 'The Gaffer', 'The misadventures of Fred Moffatt (Bill Maynard), the owner of a run down light engineering firm, Moffat Engineering Company.', '1981', '1983', 'tt0081866', 62, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(293, 2, 'comedy', 'active', 'to-the-manor-born-season-1-of-3-tv-series-1979-xvid-avi', 'To The Manor Born Series 1', 'Following her husband\'s passing, Audrey fforbes-Hamilton is forced to sell her stately home. While she comes to terms with her downward mobility, she decides to show the new owner a thing or two about \"nobility\". Series 1.', '1979', '1981', 'tt0078703', 63, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(294, 2, 'comedy', 'active', 'to-the-manor-born-season-2-of-3-tv-series-1979-xvid-avi', 'To The Manor Born Series 2', 'Following her husband\'s passing, Audrey fforbes-Hamilton is forced to sell her stately home. While she comes to terms with her downward mobility, she decides to show the new owner a thing or two about \"nobility\". Series 2.', '1979', '1981', 'tt0078703', 64, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(295, 2, 'comedy', 'active', 'to-the-manor-born-season-3-of-3-tv-series-1979-xvid-avi', 'To The Manor Born Series 3', 'Following her husband\'s passing, Audrey fforbes-Hamilton is forced to sell her stately home. While she comes to terms with her downward mobility, she decides to show the new owner a thing or two about \"nobility\".', '1979', '1981', 'tt0078703', 65, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(296, 2, 'comedy', 'active', 'to-the-manor-born-christmas-special-tv-series-1979-xvid-avi', 'Christmas Special (1979)', 'Following her husband\'s passing, Audrey fforbes-Hamilton is forced to sell her stately home. While she comes to terms with her downward mobility, she decides to show the new owner a thing or two about \"nobility\". Christmas Special (1979).', '1979', '1981', 'tt0078703', 66, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(297, 2, 'comedy', 'active', 'youre-only-young-twice-1977-81', 'You\'re Only Young Twice', 'Comedy about the residents of Paradise Lodge, retirement home for Gentle folk.', '1977', '1981', 'tt0075603', 67, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(298, 2, 'comedy', 'active', 'waiting-for-god_202401', 'Waiting For God', 'An unusual alliance develops between Diana Trent, a cynical retired photojournalist and Tom Ballard, a former accountant, while staying at the Bayview Retirement Home.', '1990', '1994', 'tt0098945', 68, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(299, 3, 'christmas', 'active', 'vintage-vhs-peanuts-classic-a-charlie-brown-christmas', 'A Charlie Brown Christmas', 'Depressed at the commercialism he sees around him, Charlie Brown tries to find a deeper meaning to Christmas.', '1965', '1965', 'tt0059026', 0, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(300, 3, 'christmas', 'active', 'a-christmas-story_202105', 'A Christmas Story', 'In the 1940s, a young boy named Ralphie Parker attempts to convince his parents, teacher, and Santa Claus that a Red Ryder Range 200 Shot BB gun really is the perfect Christmas gift.', '1983', '1983', 'tt0085334', 1, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(301, 3, 'christmas', 'active', 'a-flinstone-christmas', 'A Flintstone Christmas', 'When Santa has an accident at Fred\'s house on Christmas Eve, Fred and Barney must continue his run for him.', '1977', '1977', 'tt0193163', 2, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(302, 3, 'christmas', 'active', 'AVeryMonkeyChristmas', 'A Very Monkey Christmas', 'George and The Man In The Yellow Hat are having a merry time counting down to Christmas. But neither can decide what to give each other. Will they find the answers before Christmas morning?', '2009', '2009', 'tt1570964', 3, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(303, 3, 'christmas', 'active', 'videoplayback-2021-07-22-t-092115.332', 'A Very Thomas Christmas', 'All aboard for a special holiday delivery filled with friendship, teamwork & fun! Unwrap the holiday adventures with Thomas and his friends and best wishes for a very Thomas Christmas!', '2012', '2012', 'tt2406724', 4, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(304, 3, 'christmas', 'active', 'disneys-halloween-treat-1984-vhs', 'Disney\'s Halloween Treat', 'Contains memorable scenes from \"Snow White and the Seven Dwarfs,\" \"Fantasia,\" \"Lady and the Tramp,\" \"Peter Pan,\" \"One Hundred and One Dalmatians,\" and \"The Sword in the Stone.\"', '1984', '1984', 'tt0289920', 5, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(305, 3, 'christmas', 'active', 'TheGiftOfLoveAChristmasStory', 'Gift of Love: a Christmas Story', 'After experiencing several stressful situations within a short time - including the failure of the family business and the loss of her mother - Janet Broderick (Lee Remick) becomes ill. Falling into a deep sleep, she dreams of returning to her hometown, taking her children with her to meet her deceased loved ones.', '1983', '1983', 'tt0085593', 6, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(306, 3, 'christmas', 'active', 'mister-magoos-christmas-carol-1962', 'Mister Magoo\'s Christmas Carol', 'This musical adaptation of the classic tale by Charles Dickens stars Mr. Magoo as the cold-hearted old miser, Ebenezer Scrooge.', '1962', '1962', 'tt0123179', 7, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(307, 3, 'christmas', 'active', 'rudolph-the-red-nosed-reindeer-full-movie-1080-p-hd', 'Rudolph The Red Nosed Reindeer', 'A young reindeer Rudolph lives at the North Pole. His father is one of Santa\'s reindeer and it is expected that Rudolph will eventually be one too. However, he has a feature which is a setback and causes him to be ostracized: his red nose.', '1964', '1964', 'tt0058536', 8, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(308, 3, 'christmas', 'active', 'MiracleOn34thStreet1947', 'The Miracle on 34th Street', 'After a divorced New York mother hires a nice old man to play Santa Claus at Macy\'s, she is startled by his claim to be the genuine article. When his sanity is questioned, a lawyer defends him in court by arguing that he\'s not mistaken.', '1947', '1947', 'tt0039628', 9, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(309, 3, 'christmas', 'active', 'the-year-without-a-santa-claus-1974_202203', 'The Year Without A Santa Claus', 'When a weary and discouraged Santa Claus considers skipping his Christmas Eve run one year, Mrs. Claus and his elves set out to change his mind.', '1974', '1974', 'tt0072424', 10, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(310, 3, 'christmas', 'active', 'twas-the-night-before-christmas-1974-full-movie-freedownloadvideo.net', 'Twas the Night Before Christmas', 'When a town learns that Santa Claus has struck it off his delivery schedule due to an insulting letter, a way must be found to change his mind.', '1974', '1974', 'tt0208654', 11, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(311, 3, 'christmas', 'active', 'yogischristmas', 'Yogi\'s First Christmas', 'Yogi, Boo Boo and Cindy are awakened from hibernation and join their friends\' Christmas activities while interfering with two villains\' efforts to ruin the holiday.', '1980', '1980', 'tt0199161', 12, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(312, 3, 'easter', 'active', 'a-family-circus-easter', 'A Family Circus Easter', 'Billy, Dolly & Jeffy come up with a plan to capture the Easter Bunny for PJ.', '1982', '1982', 'tt0308247', 13, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(313, 3, 'easter', 'active', 'easter-fever-vhs', 'Easter Fever', 'A jive-talking Easter Bunny named Jack decides to retire, so his friends throw him a crazy roast before he officially hangs up his basket. A series of kooky flashbacks tells of his life-story & career, but will all this reminiscing only convince him not?', '1980', '1980', 'tt0382006', 14, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14');
INSERT INTO `playlist_shows` (`id`, `playlist_id`, `category`, `status`, `identifier`, `title`, `description`, `start_year`, `end_year`, `imdb`, `sort_order`, `thumbnail_path`, `created_at`, `updated_at`) VALUES
(314, 3, 'easter', 'active', 'here_comes_peter_cottontail_1971', 'Here Comes Peter Cottontail', 'With the help of a time machine, Peter Cottontail must rescue Easter from the hands of the malicious Irontail.', '1971', '1971', 'tt0249577', 15, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(315, 3, 'easter', 'active', 'charlie-brown-easter-beagle_202302', 'It\'s the Easter Beagle, Charlie Brown', 'The Peanuts gang go on a fun-filled Easter egg hunt, and this year they even get a glimpse of the Easter Beagle who looks suspiciously like a certain pooch.', '1974', '1974', 'tt0071679', 16, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(316, 3, 'easter', 'active', 'king-of-kings', 'King of Kings', 'The life and times of Jesus against a background of Roman paganism. Set in Palestine, depicts the Jews under Roman rule and their struggle for freedom under the leadership of Barabbas, the man who was spared while Jesus was crucified.', '1961', '1961', 'tt0055047', 17, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(317, 3, 'easter', 'active', 'PJFunnybunnysVeryCoolEaster1996.18', 'PJ Bunny: A Very Cool Easter', 'An Easter tale starring P.J. Funnybunny', '1997', '1997', 'tt0178807', 18, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(318, 3, 'easter', 'active', 'rugrats-easter-2002-vhs', 'Rugrats Easter', 'Tommy feels rejected when Spike seems to be paying all his attention to Fifi until the cause is revealed - Fifi delivers a doghouse full of puppies. Also known as \"Bow Wow Wedding Vows\".', '2002', '2002', 'tt0691302', 19, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(319, 3, 'easter', 'active', 'the-easter-story-keepers-1997-ia-copy', 'The Easter Story Keepers', 'Ben the Baker (Robert Guillaume), along with his wife Helena (Debby Boone) and their adopted children, must work together to save their Christian friends from Nero (Tim Curry) and his Roman soldiers. Ben is a Story Keeper: one who is entrusted to share the story of Jesus Christ during this time of heavy persecution.', '1998', '1998', 'tt1002678', 20, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(320, 3, 'easter', 'active', 'the-great-easter-egg-hunt-vhs-family-home-entertainment-kids', 'The Great Easter Egg Hunt', 'Peter\'s grandmother sends him an Easter basket with a stuffed bunny known as Whiskers, who becomes the envy of the other toys, because he\'s taken to school and they stay home.', '2000', '2000', 'tt2555576', 21, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(321, 3, 'easter', 'active', 'veggie-tales-an-easter-carol-vhs', 'Veggie Tales: An Easter Carol', 'An adaptation of A Christmas Carol, set on Easter and starring Mr. Nezzer.', '2004', '2004', 'tt0410839', 22, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(322, 3, 'halloween', 'active', 'a-disney-halloween-the-best-of-wdp-10-29-1987', 'A Disney Halloween', 'An off-screen narrator and the Slave in the Magic Mirror offers several spooks and humor from showcasing disaster funny villains to presenting several cartoons in this Disney Channel exclusive.', '1983', '1983', 'tt20770788', 23, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(323, 3, 'halloween', 'active', 'curious-george-a-halloween-boo-fest-2013-universal-dvd-video', 'A Halloween Boo Fest', 'Get ready for a spook-tacular good time with Curious George - in his first-ever Halloween movie. This fun-filled adventure is a bewitching treat for the whole family!', '2013', '2013', 'tt3124292', 24, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(324, 3, 'halloween', 'active', 'garfields-halloween-adventure-v2-1080p', 'Garfield\'s Halloween Adventure', 'When Garfield and Odie are out trick-or-treating, they end up at a haunted house.', '1985', '1985', 'tt0279830', 25, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(325, 3, 'halloween', 'active', '01halloweenhorror1978donaldpleasenceengsubs720ph264mp4', 'Halloween (1978)', 'Fifteen years after murdering his sister on Halloween night 1963, Michael Myers escapes from a mental hospital and returns to the small town of Haddonfield, Illinois, to kill again.', '1978', '1978', 'tt0077651', 26, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(326, 3, 'halloween', 'active', 'watch-its-the-great-pumpkin-charlie-brown', 'It\'s The Great Pumpkin, Charlie Brown', 'The Peanuts gang celebrates Halloween while Linus waits for the Great Pumpkin.', '1966', '1966', 'tt0060550', 27, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(327, 3, 'halloween', 'active', 'the-legend-of-sleepy-hollow-1980-family-mystery-adventure-tv-movie', 'Legend Of Sleepy Hollow', 'Angered that Katrina has grown fond of schoolmaster Crane, Brom Bones determines to scare off the interloper by filling his head with spooky tales of a Headless Horseman. Crane disparages the legends, until one fateful ride home in the dark of night.', '1980', '1980', 'tt0079453', 28, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(328, 3, 'halloween', 'active', '1978-witchs-night-out-a-halloween-cartoon-movie', 'Witch\'s Night Out', 'A witch, disgruntled by the fact that no one takes Halloween seriously anymore, decides to stir things up and disrupt the social gathering in her old house as well as turn a couple of kids who love monsters into actual monsters.', '1978', '1978', 'tt0078500', 29, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(329, 3, 'thanksgiving', 'active', 'macysthanksgivingdayparade1981reuploadwithcommericals', 'Macy\'s Parade (1981)', 'The 55th Annual Macy\'s Thanksgiving Day Parade from 1981.', '1981', '1981', 'tt2058648', 30, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(330, 3, 'thanksgiving', 'active', 'macys-parade-1982-nbc_202207', 'Macy\'s Parade (1982)', 'The 56th Annual Macy\'s Thanksgiving Day Parade from 1982.', '1982', '1982', 'tt1300161', 31, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(331, 3, 'thanksgiving', 'active', 'macysthanksgivingdayparade1988withcommercialsvhs_201912', 'Macy\'s Parade (1988)', 'The Macy\'s Thanksgiving Day Parade from 1988.', '1988', '1988', 'tt1433536', 32, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(332, 3, 'thanksgiving', 'active', 'macysthanksgivingdayparade1989full', 'Macy\'s Parade (1989)', 'The Macy\'s Thanksgiving Day Parade from 1989.', '1989', '1989', 'tt1182303', 33, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(333, 3, 'thanksgiving', 'active', 'charlie-brown-thanksgiving_202111', 'A Charlie Brown Thanksgiving', 'Peppermint Patty invites herself and her friends over to Charlie Brown\'s for Thanksgiving, and with Linus, Snoopy, and Woodstock, he attempts to throw together a Thanksgiving dinner.', '1973', '1973', 'tt0068359', 34, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(334, 3, 'thanksgiving', 'active', 'arthur-s-24e-00-an-arthur-thanksgiving', 'An Arthur Thanksgiving', 'It\'s Thanksgiving in Elwood City. Arthur and his friends get to participate in the parade. Arthur\'s family thinks Pal ran away from home and search for him. D.W. tries to get to know Aunt Minnie.', '2020', '2020', 'tt13473018', 35, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(335, 3, 'thanksgiving', 'active', 'B.C._The_First_Thanksgiving_1973', 'BC: The First Thanksgiving', 'To add flavor to her rock soup, the Fat Broad commands Wiley, Peter, Thor, etc. to catch a turkey. The problem is that no one knows what a turkey is, except for the turkey himself. Based on Johnny Hart\'s B.C. comic strip.', '1973', '1973', 'tt0770716', 36, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(336, 3, 'thanksgiving', 'active', 'garfieldsthanksgiving', 'Garfield\'s Thanksgiving', 'Jon falls for Garfield\'s veterinarian--who puts Garfield on a diet--and invites her to Thanksgiving dinner.', '1989', '1989', 'tt0292521', 37, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(337, 3, 'thanksgiving', 'active', '1062392039985', 'Thanksgiving That Almost Wasn\'t', 'A talking squirrel must save the holiday by rescuing a young Pilgrim boy and a young Native American boy that has gone missing in the woods on Thanksgiving day.', '1972', '1972', 'tt1230175', 38, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(338, 3, 'thanksgiving', 'active', 'the-mayflower-voyagers-1988-1080p-blu-ray-x-265-10bit-tigole', 'The Mayflower Voyagers', 'The Peanuts gang tells the story of the 1620 Mayflower voyage from England to the new world detailing the hardships they faced, how the Natives helped them survive, and ending the following autumn in a feast of Thanksgiving.', '1988', '1988', 'tt0307141', 39, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(339, 3, 'thanksgiving', 'active', 'the-mouse-on-the-mayflower-1968_202311', 'The Mouse on the Mayflower', 'The Mayflower journey as seen through the experiences of a church mouse.', '1968', '1968', 'tt0299044', 40, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(340, 3, 'thanksgiving', 'active', 'the-little-orphan-1949-restored', 'Tom & Jerry: The Little Orphan', 'The Bide-a-Wee Mouse Home has sent the orphan mouse, Nibbles, to spend Thanksgiving with Jerry. But Jerry\'s cupboard is bare, and Nibbles is always hungry.', '1949', '1949', 'tt0041592', 41, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(341, 4, 'action', 'active', 'big-trouble-little-china', 'Big Trouble In Little China', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean vulputate scelerisque lorem non vestibulum. Pellentesque pellentesque tincidunt augue at pretium.', '1986', '1986', 'tt0090728', 0, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(342, 4, 'action', 'active', 'blade_202210', 'Blade', 'A half-vampire, half-mortal man becomes a protector of the mortal race, while slaying evil vampires.', '1998', '1998', 'tt0120611', 1, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(343, 4, 'action', 'active', 'enemy.-of.-the.-state.-1998.720p.-br-rip.x-264.-bokutox.-yify', 'Enemy of the State', 'A lawyer becomes targeted by a corrupt politician and his N.S.A. goons when he accidentally receives key evidence to a politically motivated crime.', '1998', '1998', 'tt0120660', 2, NULL, '2026-07-03 15:51:14', '2026-07-03 15:51:14'),
(344, 4, 'action', 'active', 'hamburger.-hill.-1987.720p.-blu-ray.x-264.-yify', 'Hamburger Hill', 'A very realistic interpretation of one of the bloodiest battles of the Vietnam War.', '1987', '1987', 'tt0093137', 3, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(345, 4, 'action', 'active', 'in-the-line-of-fire-1993-720p', 'In The Line Of Fire', 'Secret Service agent Frank Horrigan couldn\'t save Kennedy, but he\'s determined not to let a clever assassin take out this president.', '1993', '1993', 'tt0107206', 4, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(346, 4, 'action', 'active', 'jason-and-the-argonauts-1963', 'Jason And The Argonauts', 'The legendary Greek hero leads a team of intrepid adventurers in a perilous quest for the legendary Golden Fleece.', '1963', '1963', 'tt0057197', 5, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(347, 4, 'action', 'active', 'kingdom.-of.-heaven.-2005.-directors.-cut.-720p.-brrip.x-264-fastbet-99', 'Kingdom Of Heaven', 'Balian of Ibelin travels to Jerusalem during the Crusades of the 12th century, and there he finds himself as the defender of the city and its people.', '2005', '2005', 'tt0320661', 6, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(348, 4, 'action', 'active', 'revenge.of.the.-ninja.-1983.1080p.-blu-ray.-h-264.-aac-rarbg', 'Revenge Of The Ninja', 'After ninjas killed his family, Cho and his son Kane come to America to start a new life. He opens a doll shop but is unwittingly importing heroin in the dolls. When his friend betrays him, Cho must prepare for the ultimate battle.', '1983', '1983', 'tt0086192', 7, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(349, 4, 'action', 'active', 'SmokeyAndTheBandit', 'Smokey and the Bandit', 'The Bandit is hired on to run a tractor-trailer full of beer over state lines, in hot pursuit by a pesky sheriff.', '1977', '1977', 'tt0076729', 8, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(350, 4, 'action', 'active', 'true-lies-1994-d-theater', 'True Lies', 'A fearless, globe-trotting, terrorist-battling secret agent has his life turned upside down when he discovers his wife might be having an affair with a used-car salesman while terrorists smuggle nuclear war heads into the United States.', '1994', '1994', 'tt0111503', 9, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(351, 4, 'comedy', 'active', '9-to-5', '9 to 5', 'Three secretaries turn the tables on their obnoxious boss.', '1980', '1980', 'tt0080319', 10, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(352, 4, 'comedy', 'active', 'ItsAMadMadMadMadWorld_201407', 'Its a Mad, Mad, Mad, Mad World', 'A group of motorists witnesses a car crash in the California desert, and after the driver\'s dying words indicate the location of a hidden stash of loot, they turn against each other in a race across the state to get to it.', '1963', '1963', 'tt0057193', 11, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(353, 4, 'comedy', 'active', 'kungpow_202207', 'Kung Pow: Enter The Fist', 'A rough-around-the-edges martial arts master seeks revenge for his parents death.', '2002', '2002', 'tt0240468', 12, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(354, 4, 'comedy', 'active', '9convert.com-monty-python-and-the-holy-grail-1974-1080p', 'Monty Python and the Holy Grail', 'King Arthur and his Knights of the Round Table embark on a surreal, low-budget search for the Holy Grail, encountering many, very silly obstacles.', '1975', '1975', 'tt0071853', 13, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(355, 4, 'comedy', 'active', 'stripes.-1981.720p.-blu-ray.x-264.-yify_202503', 'Stripes', 'Two friends who are dissatisfied with their jobs decide to join the army for a bit of fun.', '1981', '1981', 'tt0083131', 14, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(356, 4, 'comedy', 'active', 'the-gods-must-be-crazy-1980_202201', 'The Gods Must Be Crazy', 'A comic allegory about a traveling Bushman who encounters modern civilization and its stranger aspects, including a clumsy scientist and a band of revolutionaries.', '1980', '1980', 'tt0080801', 15, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(357, 4, 'drama', 'active', 'the.-color.-purple.-1985.720p.-blu-ray.x-264-yts.-lt', ' The Color Purple', 'A tale spanning forty years in the life of Celie, an African-American woman living in the South who survives incredible abuse and bigotry.', '1985', '1985', 'tt0088939', 16, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(358, 4, 'drama', 'active', 'casablanca.-1942.720.x-264.-yify_202502', 'Casablanca', 'A cynical expatriate American cafe owner struggles to decide whether or not to help his former lover and her fugitive husband escape the Nazis in French Morocco.', '1942', '1942', 'tt0034583', 17, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(359, 4, 'drama', 'active', 'cool-hand-luke-1967', 'Cool Hand Luke', 'A laid-back Southern man is sentenced to two years in a rural prison, but refuses to conform.', '1967', '1967', 'tt0061512', 18, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(360, 4, 'drama', 'active', 'dead-poets-society-1989-720p-blu-ray-x-264-yify', 'Dead Poets Society', 'Maverick teacher John Keating returns in 1959 to the prestigious New England boys\' boarding school where he was once a star student, using poetry to embolden his pupils to new heights of self-expression.', '1989', '1989', 'tt0097165', 19, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(361, 4, 'drama', 'active', 'empire.of.the.-sun.-1987.720p.-blu-ray.x-264.-yify', 'Empire of the Sun', 'A young English boy struggles to survive under Japanese occupation of China during World War II.', '1987', '1987', 'tt0092965', 20, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(362, 4, 'drama', 'active', 'little.-miss.-sunshine.-2006.720p.-blu-ray.x-264.-yify_202503', 'Little Miss Sunshine', 'A family determined to get their young daughter into the finals of a beauty pageant take a cross-country trip in their VW bus.', '2006', '2006', 'tt0449059', 21, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(363, 4, 'drama', 'active', 'manhunter.-1986-255910939230', 'Manhunter', 'Former FBI profiler Will Graham returns to service to pursue a deranged serial killer dubbed \"the Tooth Fairy\" by the media.', '1986', '1986', 'tt0091474', 22, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(364, 4, 'drama', 'active', 'memento.-2000.720p.-blu-ray.x-264.-yify_202503', 'Memento', 'A former insurance investigator who now suffers from anterograde amnesia uses notes and tattoos to hunt down his wife\'s murderer.', '2000', '2000', 'tt0209144', 23, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(365, 4, 'drama', 'active', 'Shogun_Miniseries', 'Shogun (miniseries)', 'An English navigator becomes both a player and pawn in the complex political games in feudal Japan.', '1980', '1980', 'tt0080274', 24, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(366, 4, 'drama', 'active', 'TheMagicSwordWidescreenQualityUpgrade', 'The Magic Sword', 'The son of a sorceress, armed with weapons, armor and six magically summoned knights, embarks on a quest to save a princess from a vengeful wizard.', '1962', '1962', 'tt0056211', 25, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(367, 4, 'family', 'active', 'Gullivers.Travels.1939.1080p', 'Gulliver\'s Travels', 'A doctor washes ashore on an island inhabited by little people.', '1939', '1939', 'tt0031397', 26, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(368, 4, 'family', 'active', 'race-for-your-life-charlie-brown-1982-laserdisc', 'Race For Your Life Charlie Brown!', 'The Peanuts gang goes to summer camp, and they participate in a river-raft race against some cheating bullies.', '1977', '1977', 'tt0076591', 27, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(369, 4, 'family', 'active', 'the-borrowers', 'The Borrowers', 'An eight-year-old boy discovers a family of tiny people, only a few inches tall, living beneath the floorboards of a Victorian country home.', '1973', '1973', 'tt0069817', 28, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(370, 4, 'family', 'active', 'the-neverending-story-1984-720p.-bluray.x-264', 'The Neverending Story', 'A troubled boy dives into a wondrous fantasy world through the pages of a mysterious book.', '1984', '1984', 'tt0088323', 29, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(371, 4, 'family', 'active', 'the.-parent.-trap.-1998.720p.-blu-ray.x-264-yts.-am', 'The Parent Trap', 'Identical twins Annie and Hallie, separated at birth and each raised by one of their biological parents, discover each other for the first time at summer camp and make a plan to bring their wayward parents back together.', '1998', '1998', 'tt0120783', 30, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(372, 4, 'horror', 'active', 'creature-from-the-black-lagoon-1954-colorized', 'Creature from the Black Lagoon', 'A strange prehistoric beast lurks in the depths of the Amazonian jungle. A group of scientists try to capture the animal and bring it back to civilization for study.', '1954', '1954', 'tt0046876', 31, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(373, 4, 'horror', 'active', 'Night.Of.The.Living.Dead_1080p/NightOfTheLivingDead.m2ts', 'Night of the Living Dead', 'A ragtag group of Pennsylvanians barricade themselves in an old farmhouse to remain safe from a horde of flesh-eating ghouls that are ravaging the Northeast of the United States.', '1968', '1968', 'tt0063350', 32, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(374, 4, 'horror', 'active', 'tarantula-1955-colorized', 'Tarantula', 'A spider escapes from an isolated Arizona desert laboratory experimenting in gigantism and grows to tremendous size as it wreaks havoc on the local inhabitants.', '1955', '1955', 'tt0048696', 33, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(375, 4, 'romance', 'active', 'before.-sunrise.-1995.720p.-blu-ray.x-264-yts.-am', 'Before Sunrise', 'A young man and woman meet on a train in Europe, and wind up spending one evening together in Vienna. Unfortunately, both know that this will probably be their only night together.', '1995', '1995', 'tt0112471', 34, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(376, 4, 'romance', 'active', 'dirty.-dancing.-1987.720p.-br-rip.x-264.-yify', 'Dirty Dancing', 'Spending the summer at a Catskills resort with her family, Frances \"Baby\" Houseman falls in love with the camp\'s dance instructor, Johnny Castle.', '1987', '1987', 'tt0092890', 35, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(377, 4, 'romance', 'active', 'jerry.-maguire.-1996.-br-rip.-720p.x-264.-yify_202503', 'Jerry Maguire', 'When a sports agent has a moral epiphany and is fired for expressing it, he decides to put his new philosophy to the test as an independent agent with the only athlete who stays with him and his former colleague.', '1996', '1996', 'tt0116695', 36, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(378, 4, 'romance', 'active', 'notting.-hill.-1999.720p.-br-rip.x-264.-bokutox.-yify', 'Notting Hill', 'A set of circumstances makes Anna Scott, a famous actress, fall in love with William Thacker, owner of a bookstore in Notting Hill. But the paparazzi\'s fascination with her complicates their bond.', '1999', '1999', 'tt0125439', 37, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(379, 4, 'romance', 'active', 'pride-and-prejudice-1995-miniseries', 'Pride and Prejudice (miniseries)', 'While the arrival of wealthy gentlemen sends her marriage-minded mother into a frenzy, willful and opinionated Elizabeth Bennet matches wits with haughty Mr. Darcy.', '1995', '1995', 'tt0112130', 38, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(380, 4, 'sci-fi', 'active', 'batteries.-not.-included.-1987.720p.-blu-ray.x-264-yts.-ag', 'Batteries Not Included', 'Aliens help a feisty old New York couple in their battle against the ruthless land developer who\'s out to evict them.', '1987', '1987', 'tt0092494', 39, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(381, 4, 'sci-fi', 'active', 'Dune19843640x272435mb', 'Dune', 'A Duke\'s son leads desert warriors against the galactic emperor and his father\'s evil nemesis to free their desert world from the emperor\'s rule.', '1984', '1984', 'tt0087182', 40, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(382, 4, 'sci-fi', 'active', 'galaxy-quest_DVD', 'Galaxy Quest', 'The alumni cast of a space opera television series have to play their roles as the real thing when an alien race needs their help. However, they also have to defend both Earth and the alien race from a reptilian warlord.', '1999', '1999', 'tt0177789', 41, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(383, 4, 'sci-fi', 'active', 'soylent-green-1973_20210310', 'Soylent Green', 'A nightmarish futuristic fantasy about the controlling power of big corporations and an innocent cop who stumbles on the truth.', '1973', '1973', 'tt0070723', 42, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(384, 4, 'sci-fi', 'active', 'Superman1978BRRipDualAudio720pByeArnavSinha_201903', 'Superman (1978)', 'An alien orphan is sent from his dying planet to Earth, where he grows up to become his adoptive home\'s first and greatest superhero.', '1978', '1978', 'tt0078346', 43, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(385, 4, 'sci-fi', 'active', 'day-the-earth-stood-still-1951', 'The Day the Earth Stood Still', 'An alien lands in Washington, D.C. and tells the people of Earth that they must live peacefully or be destroyed as a danger to other planets.', '1951', '1951', 'tt0043456', 44, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(386, 4, 'sci-fi', 'active', 'the-fury-1978-kirk-douglas-john-cassavetes-384756681456', 'The Fury', 'A former CIA agent uses the talents of a young psychic to help retrieve his telekinetic son from a shadowy secret government agency.', '1978', '1978', 'tt0077588', 45, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(387, 4, 'sci-fi', 'active', 'tron_20241110', 'Tron (1982)', 'A computer hacker is abducted into a digital world and forced to participate in gladiatorial games where his only chance of escape is with the help of a heroic security program.', '1982', '1982', 'tt0084827', 46, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(388, 4, 'western', 'active', 'mclintok_widescreen', 'McLintock!', 'Wealthy rancher G. W. McLintock uses his power and influence in the territory to keep the peace between farmers, ranchers, land-grabbers, Indians and corrupt government officials.', '1963', '1963', 'tt0057298', 47, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(389, 4, 'western', 'active', 'TheNevadan', 'The Nevadan', 'A mysterious stranger crosses paths with an outlaw bank robber and a greedy rancher.', '1950', '1950', 'tt0042782', 48, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(390, 4, 'western', 'active', 'young-guns', 'Young Guns', 'A group of young gunmen, led by Billy the Kid, become deputies to avenge the murder of the rancher who became their benefactor. But when Billy takes their authority too far, they become the hunted.', '1988', '1988', 'tt0096487', 49, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(391, 4, 'western', 'active', 'young-guns-ii', 'Young Guns II', 'Billy the Kid and his band of outlaws are pursued across New Mexico territory by Sheriff Pat Garrett, who the young gunslingers must face-off with if they are to reach the safety of the border.', '1990', '1990', 'tt0100994', 50, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(392, 4, 'drama', 'active', 'Three-Days-of-the-Condor_1975_AI_1080p.BluRay.10b.HEVC.DTS-HD.MA.5.1_BLUDUAINE', 'Three Days of the Condor', 'A bookish CIA researcher in Manhattan finds all his co-workers dead, and must outwit those responsible until he figures out who he can really trust.', '1975', '1975', 'tt0073802', 51, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(393, 4, 'animation', 'active', 'howls.-moving.-castle.-2004.1080p.-bdrip.-dual.-audio.-aac-5.1.10bits.x-265-rapta', 'Howl\'s Moving Castle', 'When an unconfident young woman is cursed with an old body by a spiteful witch, her only chance of breaking the spell lies with a self-indulgent yet insecure young wizard and his companions in his legged, walking castle.', '2004', '2004', 'tt0347149', 52, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(394, 4, 'drama', 'active', 'the.-longest.-day.-1962', 'The Longest Day', 'The events of D-Day, told on a grand scale from both the Allied and German points of view.', '1962', '1962', 'tt0056197', 53, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(395, 4, 'drama', 'active', 'murder-on-the-orient-express-1974', 'Murder on the Orient Express', 'Returning home from Istanbul on the Orient Express, renowned Belgian detective Hercule Poirot is called upon to solve the murder of an American businessman aboard the train. The suspects are numerous and the clues confusing.', '1974', '1974', 'tt0071877', 54, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15'),
(396, 4, 'horror', 'active', 'the-silence-of-the-lambs-1991_202405', 'The Silence Of The Lambs', 'A young FBI cadet is aided by a manipulative cannibal killer in her pursuit of a madman who skins his victims.', '1991', '1991', 'tt0102926', 55, NULL, '2026-07-03 15:51:15', '2026-07-03 15:51:15');

-- --------------------------------------------------------

--
-- Table structure for table `problem_reports`
--

CREATE TABLE `problem_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `playlist_id` int(10) UNSIGNED DEFAULT NULL,
  `playlist_show_id` int(10) UNSIGNED DEFAULT NULL,
  `identifier` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `imdb` varchar(50) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'reported',
  `report_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `archive_api_error` tinyint(1) NOT NULL DEFAULT 0,
  `first_reported_at` datetime NOT NULL,
  `last_reported_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `problem_report_ips`
--

CREATE TABLE `problem_report_ips` (
  `id` int(10) UNSIGNED NOT NULL,
  `problem_report_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reported_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `thumbnail_files`
--

CREATE TABLE `thumbnail_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `playlist_show_id` int(10) UNSIGNED NOT NULL,
  `filename` varchar(255) NOT NULL,
  `relative_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `status`, `created_at`, `last_login_at`, `updated_at`) VALUES
(1, 'admin', '$2y$12$yC4l6qMrscz3JZ3KxpeJjeJN5oxViSULdjqSQmbe0LlbwRFBVXGRS', 'admin', 'active', '2025-10-04 12:07:15', '2026-07-03 21:16:16', '2026-07-03 17:16:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `playlists`
--
ALTER TABLE `playlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_playlists_filename` (`filename`),
  ADD KEY `idx_playlists_is_default` (`is_default`),
  ADD KEY `idx_playlists_sort_order` (`sort_order`);

--
-- Indexes for table `playlist_shows`
--
ALTER TABLE `playlist_shows`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_playlist_shows_playlist_identifier` (`playlist_id`,`identifier`),
  ADD KEY `idx_playlist_shows_status` (`status`),
  ADD KEY `idx_playlist_shows_category` (`category`),
  ADD KEY `idx_playlist_shows_sort_order` (`sort_order`);

--
-- Indexes for table `problem_reports`
--
ALTER TABLE `problem_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_problem_reports_status` (`status`),
  ADD KEY `idx_problem_reports_playlist_id` (`playlist_id`),
  ADD KEY `idx_problem_reports_identifier` (`identifier`),
  ADD KEY `fk_problem_reports_show` (`playlist_show_id`);

--
-- Indexes for table `problem_report_ips`
--
ALTER TABLE `problem_report_ips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_problem_report_ips_ip` (`ip_address`),
  ADD KEY `idx_problem_report_ips_report` (`problem_report_id`);

--
-- Indexes for table `thumbnail_files`
--
ALTER TABLE `thumbnail_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_thumbnail_files_show_filename` (`playlist_show_id`,`filename`),
  ADD KEY `idx_thumbnail_files_filename` (`filename`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `app_settings`
--
ALTER TABLE `app_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `playlists`
--
ALTER TABLE `playlists`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `playlist_shows`
--
ALTER TABLE `playlist_shows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=397;

--
-- AUTO_INCREMENT for table `problem_reports`
--
ALTER TABLE `problem_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `problem_report_ips`
--
ALTER TABLE `problem_report_ips`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `thumbnail_files`
--
ALTER TABLE `thumbnail_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `playlist_shows`
--
ALTER TABLE `playlist_shows`
  ADD CONSTRAINT `fk_playlist_shows_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `problem_reports`
--
ALTER TABLE `problem_reports`
  ADD CONSTRAINT `fk_problem_reports_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_problem_reports_show` FOREIGN KEY (`playlist_show_id`) REFERENCES `playlist_shows` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `problem_report_ips`
--
ALTER TABLE `problem_report_ips`
  ADD CONSTRAINT `fk_problem_report_ips_report` FOREIGN KEY (`problem_report_id`) REFERENCES `problem_reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `thumbnail_files`
--
ALTER TABLE `thumbnail_files`
  ADD CONSTRAINT `fk_thumbnail_files_show` FOREIGN KEY (`playlist_show_id`) REFERENCES `playlist_shows` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
