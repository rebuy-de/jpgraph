<?php
/*=======================================================================
 // File:         QRCAPACITY.PHP
 // Description:  Capacity information for QR codes used in encodation
 // Created:      2008-07-14
 // Ver:          $Id: qrcapacity.inc.php 1106 2009-02-22 20:16:35Z ljp $
 //
 // Copyright (c) 2008 Asial Corporation. All rights reserved.
 //========================================================================
 */

class QRCapacity {

    // NOTE: These constants can not be changed! The tables are built in this order
    const ErrL = 0;
    const ErrM = 1;
    const ErrQ = 2;
    const ErrH = 3;

    // The number of payload data for each version and error correction level
    // Each version has four different data capacities depending on which error
    // correction level that has been chosen
    private $iDataCapacity = array(
    /* L   M   Q   H */
    19, 16, 13, 9,
    34, 28, 22, 16,
    55, 44, 34, 26,
    80, 64, 48, 36,
    108, 86, 62, 46,
    136, 108, 76, 60,
    156, 124, 88, 66,
    194, 154, 110, 86,
    232, 182, 132, 100,
    274, 216, 154, 122,
    324, 254, 180, 140,
    370, 290, 206, 158,
    428, 334, 244, 180,
    461, 365, 261, 197,
    523, 415, 295, 223,
    589, 453, 325, 253,
    647, 507, 367, 283,
    721, 563, 397, 313,
    795, 627, 445, 341,
    861, 669, 485, 385,
    932, 714, 512, 406,
    1006, 782, 568, 442,
    1094, 860, 614, 464,
    1174, 914, 664, 514,
    1276, 1000, 718, 538,
    1370, 1062, 754, 596,
    1468, 1128, 808, 628,
    1531, 1193, 871, 661,
    1631, 1267, 911, 701,
    1735, 1373, 985, 745,
    1843, 1455, 1033, 793,
    1955, 1541, 1115, 845,
    2071, 1631, 1171, 901,
    2191, 1725, 1231, 961,
    2306, 1812, 1286, 986,
    2434, 1914, 1354, 1054,
    2566, 1992, 1426, 1096,
    2702, 2102, 1502, 1142,
    2812, 2216, 1582, 1222,
    2956, 2334, 1666, 1276
    );
    // The structure of the error correction code words for each version of the
    // QR Matrix. The table has the following structure
    // ( VERSION NUMBER,
    // ERROR LEVEL,
    // TOTAL NUMBER OF SYMBOLS,
    // TOTAL ERROR CORRECTING WORD,
    // [ NUMBER OF BLOCKS (TOTAL WORDS IN BLOCK, DATA WORDS IN BLOCK, CORRECTION CAPACITY)) ]
    // )
    // The large matrix use two different block structures so the larger matrix has two
    // set of the error structure
    //
    private $iQRStructure = array(array(1, 'L', 26, 7, 1, array(26, 19, 2)),
    array(1, 'M', 26, 10, 1, array(26, 16, 4)),
    array(1, 'Q', 26, 13, 1, array(26, 13, 6)),
    array(1, 'H', 26, 17, 1, array(26, 9, 8)),

    array(2, 'L', 44, 10, 1, array(44, 34, 4)),
    array(2, 'M', 44, 16, 1, array(44, 28, 8)),
    array(2, 'Q', 44, 22, 1, array(44, 22, 11)),
    array(2, 'H', 44, 28, 1, array(44, 16, 14)),

    array(3, 'L', 70, 15, 1, array(70, 55, 7)),
    array(3, 'M', 70, 26, 1, array(70, 44, 13)),
    array(3, 'Q', 70, 36, 2, array(35, 17, 9)),
    array(3, 'H', 70, 44, 2, array(35, 13, 11)),

    array(4, 'L', 100, 20, 1, array(100, 80, 10)),
    array(4, 'M', 100, 36, 2, array(50, 32, 9)),
    array(4, 'Q', 100, 52, 2, array(50, 24, 13)),
    array(4, 'H', 100, 64, 4, array(25, 9, 8)),

    array(5, 'L', 134, 26, 1, array(134, 108, 13)),
    array(5, 'M', 134, 48, 2, array(67, 43, 12)),
    array(5, 'Q', 134, 72, 2, 2, array(33, 15, 9), array(34, 16, 9)),
    array(5, 'H', 134, 88, 2, 2, array(33, 11, 11), array(34, 12, 11)),

    array(6, 'L', 172, 36, 2, array(86, 68, 9)),
    array(6, 'M', 172, 64, 4, array(43, 27, 8)),
    array(6, 'Q', 172, 96, 4, array(43, 19, 12)),
    array(6, 'H', 172, 112, 4, array(43, 15, 14)),

    array(7, 'L', 196, 40, 2, array(98, 78, 10)),
    array(7, 'M', 196, 72, 4, array(49, 31, 9)),
    array(7, 'Q', 196, 108, 2, 4, array(32, 14, 9), array(33, 15, 9)),
    array(7, 'H', 196, 130, 4, 1, array(39, 13, 13), array(40, 14, 13)),

    array(8, 'L', 242, 48, 2, array(121, 97, 12)),
    array(8, 'M', 242, 88, 2, 2, array(60, 38, 11), array(61, 39, 11)),
    array(8, 'Q', 242, 132, 4, 2, array(40, 18, 11), array(41, 19, 11)),
    array(8, 'H', 242, 156, 4, 2, array(40, 14, 13), array(41, 15, 13)),

    array(9, 'L', 292, 60, 2, array(146, 116, 15)),
    array(9, 'M', 292, 110, 3, 2, array(58, 36, 11), array(59, 37, 11)),
    array(9, 'Q', 292, 160, 4, 4, array(36, 16, 10), array(37, 17, 10)),
    array(9, 'H', 292, 192, 4, 4, array(36, 12, 12), array(37, 13, 12)),

    array(10, 'L', 346, 72, 2, 2, array(86, 68, 9), array(87, 69, 9)),
    array(10, 'M', 346, 130, 4, 1, array(69, 43, 13), array(70, 44, 13)),
    array(10, 'Q', 346, 192, 6, 2, array(43, 19, 12), array(44, 20, 12)),
    array(10, 'H', 346, 224, 6, 2, array(43, 15, 14), array(44, 16, 14)),

    array(11, 'L', 404, 80, 4, array(101, 81, 10)),
    array(11, 'M', 404, 150, 1, 4, array(80, 50, 15), array(81, 51, 15)),
    array(11, 'Q', 404, 224, 4, 4, array(50, 22, 14), array(51, 23, 14)),
    array(11, 'H', 404, 264, 3, 8, array(36, 12, 12), array(37, 13, 12)),

    array(12, 'L', 466, 96, 2, 2, array(116, 92, 12), array(117, 93, 12)),
    array(12, 'M', 466, 176, 6, 2, array(58, 36, 11), array(59, 37, 11)),
    array(12, 'Q', 466, 260, 4, 6, array(46, 20, 13), array(47, 21, 13)),
    array(12, 'H', 466, 308, 7, 4, array(42, 14, 14), array(43, 15, 14)),

    array(13, 'L', 532, 104, 4, array(133, 107, 13)),
    array(13, 'M', 532, 198, 8, 1, array(59, 37, 11), array(60, 38, 11)),
    array(13, 'Q', 532, 288, 8, 4, array(44, 20, 12), array(45, 21, 12)),
    array(13, 'H', 532, 352, 12, 4, array(33, 11, 11), array(34, 12, 11)),

    array(14, 'L', 581, 120, 3, 1, array(145, 115, 15), array(146, 116, 15)),
    array(14, 'M', 581, 216, 4, 5, array(64, 40, 12), array(65, 41, 12)),
    array(14, 'Q', 581, 320, 11, 5, array(36, 16, 10), array(37, 17, 10)),
    array(14, 'H', 581, 384, 11, 5, array(36, 12, 12), array(37, 13, 12)),

    array(15, 'L', 655, 132, 5, 1, array(109, 87, 11), array(110, 88, 11)),
    array(15, 'M', 655, 240, 5, 5, array(65, 41, 12), array(66, 42, 12)),
    array(15, 'Q', 655, 360, 5, 7, array(54, 24, 15), array(55, 25, 15)),
    array(15, 'H', 655, 432, 11, 7, array(36, 12, 12), array(37, 13, 12)),

    array(16, 'L', 733, 144, 5, 1, array(122, 98, 12), array(123, 99, 12)),
    array(16, 'M', 733, 280, 7, 3, array(73, 45, 14), array(74, 46, 14)),
    array(16, 'Q', 733, 408, 15, 2, array(43, 19, 12), array(44, 20, 12)),
    array(16, 'H', 733, 480, 3, 13, array(45, 15, 15), array(46, 16, 15)),

    array(17, 'L', 815, 168, 1, 5, array(135, 107, 14), array(136, 108, 14)),
    array(17, 'M', 815, 308, 10, 1, array(74, 46, 14), array(75, 47, 14)),
    array(17, 'Q', 815, 448, 1, 15, array(50, 22, 14), array(51, 23, 14)),
    array(17, 'H', 815, 532, 2, 17, array(42, 14, 14), array(43, 15, 14)),

    array(18, 'L', 901, 180, 5, 1, array(150, 120, 15), array(151, 121, 15)),
    array(18, 'M', 901, 338, 9, 4, array(69, 43, 13), array(70, 44, 13)),
    array(18, 'Q', 901, 504, 17, 1, array(50, 22, 14), array(51, 23, 14)),
    array(18, 'H', 901, 588, 2, 19, array(42, 14, 14), array(43, 15, 14)),

    array(19, 'L', 991, 196, 3, 4, array(141, 113, 14), array(142, 114, 14)),
    array(19, 'M', 991, 364, 3, 11, array(70, 44, 13), array(71, 45, 13)),
    array(19, 'Q', 991, 546, 17, 4, array(47, 21, 13), array(48, 22, 13)),
    array(19, 'H', 991, 650, 9, 16, array(39, 13, 13), array(40, 14, 13)),

    array(20, 'L', 1085, 224, 3, 5, array(135, 107, 14), array(136, 108, 14)),
    array(20, 'M', 1085, 416, 3, 13, array(67, 41, 13), array(68, 42, 13)),
    array(20, 'Q', 1085, 600, 15, 5, array(54, 24, 15), array(55, 25, 15)),
    array(20, 'H', 1085, 700, 15, 10, array(43, 15, 14), array(44, 16, 14)),

    array(21, 'L', 1156, 224, 4, 4, array(144, 116, 14), array(145, 117, 14)),
    array(21, 'M', 1156, 442, 17, array(68, 42, 13)),
    array(21, 'Q', 1156, 644, 17, 6, array(50, 22, 14), array(51, 23, 14)),
    array(21, 'H', 1156, 750, 19, 6, array(46, 16, 15), array(47, 17, 15)),

    array(22, 'L', 1258, 252, 2, 7, array(139, 111, 14), array(140, 112, 14)),
    array(22, 'M', 1258, 476, 17, array(74, 46, 14)),
    array(22, 'Q', 1258, 690, 7, 16, array(54, 24, 15), array(55, 25, 15)),
    array(22, 'H', 1258, 816, 34, array(37, 13, 12)),

    array(23, 'L', 1364, 270, 4, 5, array(151, 121, 15), array(152, 122, 15)),
    array(23, 'M', 1364, 504, 4, 14, array(75, 47, 14), array(76, 48, 14)),
    array(23, 'Q', 1364, 750, 11, 14, array(54, 24, 15), array(55, 25, 15)),
    array(23, 'H', 1364, 900, 16, 14, array(45, 15, 15), array(46, 16, 15)),

    array(24, 'L', 1474, 300, 6, 4, array(147, 117, 15), array(148, 118, 15)),
    array(24, 'M', 1474, 560, 6, 14, array(73, 45, 14), array(74, 46, 14)),
    array(24, 'Q', 1474, 810, 11, 16, array(54, 24, 15), array(55, 25, 15)),
    array(24, 'H', 1474, 960, 30, 2, array(46, 16, 15), array(47, 17, 15)),

    array(25, 'L', 1588, 312, 8, 4, array(132, 106, 13), array(133, 107, 13)),
    array(25, 'M', 1588, 588, 8, 13, array(75, 47, 14), array(76, 48, 14)),
    array(25, 'Q', 1588, 870, 7, 22, array(54, 24, 15), array(55, 25, 15)),
    array(25, 'H', 1588, 1050, 22, 13, array(45, 15, 15), array(46, 16, 15)),

    array(26, 'L', 1706, 336, 10, 2, array(142, 114, 14), array(143, 115, 14)),
    array(26, 'M', 1706, 644, 19, 4, array(74, 46, 14), array(75, 47, 14)),
    array(26, 'Q', 1706, 952, 28, 6, array(50, 22, 14), array(51, 23, 14)),
    array(26, 'H', 1706, 1110, 33, 4, array(46, 16, 15), array(47, 17, 15)),

    array(27, 'L', 1828, 360, 8, 4, array(152, 122, 15), array(153, 123, 15)),
    array(27, 'M', 1828, 700, 22, 3, array(73, 45, 14), array(74, 46, 14)),
    array(27, 'Q', 1828, 1020, 8, 26, array(53, 23, 15), array(54, 24, 15)),
    array(27, 'H', 1828, 1200, 12, 28, array(45, 15, 15), array(46, 16, 15)),

    array(28, 'L', 1921, 390, 3, 10, array(147, 117, 15), array(148, 118, 15)),
    array(28, 'M', 1921, 728, 3, 23, array(73, 45, 14), array(74, 46, 14)),
    array(28, 'Q', 1921, 1050, 4, 31, array(54, 24, 15), array(55, 25, 15)),
    array(28, 'H', 1921, 1260, 11, 31, array(45, 15, 15), array(46, 16, 15)),

    array(29, 'L', 2051, 420, 7, 7, array(146, 116, 15), array(147, 117, 15)),
    array(29, 'M', 2051, 784, 21, 7, array(73, 45, 14), array(74, 46, 14)),
    array(29, 'Q', 2051, 1140, 1, 37, array(53, 23, 15), array(54, 24, 15)),
    array(29, 'H', 2051, 1350, 19, 26, array(45, 15, 15), array(46, 16, 15)),

    array(30, 'L', 2185, 450, 5, 10, array(145, 115, 15), array(146, 116, 15)),
    array(30, 'M', 2185, 812, 19, 10, array(75, 47, 14), array(76, 48, 14)),
    array(30, 'Q', 2185, 1200, 15, 25, array(54, 24, 15), array(55, 25, 15)),
    array(30, 'H', 2185, 1440, 23, 25, array(45, 15, 15), array(46, 16, 15)),

    array(31, 'L', 2323, 480, 13, 3, array(145, 115, 15), array(146, 116, 15)),
    array(31, 'M', 2323, 868, 2, 29, array(74, 46, 14), array(75, 47, 14)),
    array(31, 'Q', 2323, 1290, 42, 1, array(54, 24, 15), array(55, 25, 15)),
    array(31, 'H', 2323, 1530, 23, 28, array(45, 15, 15), array(46, 16, 15)),

    array(32, 'L', 2465, 510, 17, array(145, 115, 15)),
    array(32, 'M', 2465, 924, 10, 23, array(74, 46, 14), array(75, 47, 14)),
    array(32, 'Q', 2465, 1350, 10, 35, array(54, 24, 15), array(55, 25, 15)),
    array(32, 'H', 2465, 1620, 19, 35, array(45, 15, 15), array(46, 16, 15)),

    array(33, 'L', 2611, 540, 17, 1, array(145, 115, 15), array(146, 116, 15)),
    array(33, 'M', 2611, 980, 14, 21, array(74, 46, 14), array(75, 47, 14)),
    array(33, 'Q', 2611, 1440, 29, 19, array(54, 24, 15), array(55, 25, 15)),
    array(33, 'H', 2611, 1710, 11, 46, array(45, 15, 15), array(46, 16, 15)),

    array(34, 'L', 2761, 570, 13, 6, array(145, 115, 15), array(146, 116, 15)),
    array(34, 'M', 2761, 1036, 14, 23, array(74, 46, 14), array(75, 47, 14)),
    array(34, 'Q', 2761, 1530, 44, 7, array(54, 24, 15), array(55, 25, 15)),
    array(34, 'H', 2761, 1800, 59, 1, array(46, 16, 15), array(47, 17, 15)),

    array(35, 'L', 2876, 570, 12, 7, array(151, 121, 15), array(152, 122, 15)),
    array(35, 'M', 2876, 1064, 12, 26, array(75, 47, 14), array(76, 48, 14)),
    array(35, 'Q', 2876, 1590, 39, 14, array(54, 24, 15), array(55, 25, 15)),
    array(35, 'H', 2876, 1890, 22, 41, array(45, 15, 15), array(46, 16, 15)),

    array(36, 'L', 3034, 600, 6, 14, array(151, 121, 15), array(152, 122, 15)),
    array(36, 'M', 3034, 1120, 6, 34, array(75, 47, 14), array(76, 48, 14)),
    array(36, 'Q', 3034, 1680, 46, 10, array(54, 24, 15), array(55, 25, 15)),
    array(36, 'H', 3034, 1980, 2, 64, array(45, 15, 15), array(46, 16, 15)),

    array(37, 'L', 3196, 630, 17, 4, array(152, 122, 15), array(153, 123, 15)),
    array(37, 'M', 3196, 1204, 29, 14, array(74, 46, 14), array(75, 47, 14)),
    array(37, 'Q', 3196, 1770, 49, 10, array(54, 24, 15), array(55, 25, 15)),
    array(37, 'H', 3196, 2100, 24, 46, array(45, 15, 15), array(46, 16, 15)),

    array(38, 'L', 3362, 660, 4, 18, array(152, 122, 15), array(153, 123, 15)),
    array(38, 'M', 3362, 1260, 13, 32, array(74, 46, 14), array(75, 47, 14)),
    array(38, 'Q', 3362, 1860, 48, 14, array(54, 24, 15), array(55, 25, 15)),
    array(38, 'H', 3362, 2220, 42, 32, array(45, 15, 15), array(46, 16, 15)),

    array(39, 'L', 3532, 720, 20, 4, array(147, 117, 15), array(148, 118, 15)),
    array(39, 'M', 3532, 1316, 40, 7, array(75, 47, 14), array(76, 48, 14)),
    array(39, 'Q', 3532, 1950, 43, 22, array(54, 24, 15), array(55, 25, 15)),
    array(39, 'H', 3532, 2310, 10, 67, array(45, 15, 15), array(46, 16, 15)),

    array(40, 'L', 3706, 750, 19, 6, array(148, 118, 15), array(149, 119, 15)),
    array(40, 'M', 3706, 1372, 18, 31, array(75, 47, 14), array(76, 48, 14)),
    array(40, 'Q', 3706, 2040, 34, 34, array(54, 24, 15), array(55, 25, 15)),
    array(40, 'H', 3706, 2430, 20, 61, array(45, 15, 15), array(46, 16, 15))
    );

    // VERSION (Number of alignment patterns, row/column, ...)
    // The actual positions are created by permutation of these row/col coordiates
    // as long as they don't collide with the corder finder patterns
    // Top left corner of matrix is (0,0)
    private $iAlignmentPatterns = array(
    1 => array(0),
    2 => array(1, 6, 18),
    3 => array(1, 6, 22),
    4 => array(1, 6, 26),
    5 => array(1, 6, 30),
    6 => array(1, 6, 34),
    7 => array(6, 6, 22, 38),
    8 => array(6, 6, 24, 42),
    9 => array(6, 6, 26, 46),
    10 => array(6, 6, 28, 50),
    11 => array(6, 6, 30, 54),
    12 => array(6, 6, 32, 58),
    13 => array(6, 6, 34, 62),
    14 => array(13, 6, 26, 46, 66),
    15 => array(13, 6, 26, 48, 70),
    16 => array(13, 6, 26, 50, 74),
    17 => array(13, 6, 30, 54, 78),
    18 => array(13, 6, 30, 56, 82),
    19 => array(13, 6, 30, 58, 86),
    20 => array(13, 6, 34, 62, 90),
    21 => array(22, 6, 28, 50, 72, 94),
    22 => array(22, 6, 26, 50, 74, 98),
    23 => array(22, 6, 30, 54, 78, 102),
    24 => array(22, 6, 28, 54, 80, 106),
    25 => array(22, 6, 32, 58, 84, 110),
    26 => array(22, 6, 30, 58, 86, 114),
    27 => array(22, 6, 34, 62, 90, 118),
    28 => array(33, 6, 26, 50, 74, 98, 122),
    29 => array(33, 6, 30, 54, 78, 102, 126),
    30 => array(33, 6, 26, 52, 78, 104, 130),
    31 => array(33, 6, 30, 56, 82, 108, 134),
    32 => array(33, 6, 34, 60, 86, 112, 138),
    33 => array(33, 6, 30, 58, 86, 114, 142),
    34 => array(33, 6, 34, 62, 90, 118, 146),
    35 => array(46, 6, 30, 54, 78, 102, 126, 150),
    36 => array(46, 6, 24, 50, 76, 102, 128, 154),
    37 => array(46, 6, 28, 54, 80, 106, 132, 158),
    38 => array(46, 6, 32, 58, 84, 110, 136, 162),
    39 => array(46, 6, 26, 54, 82, 110, 138, 166),
    40 => array(46, 6, 30, 58, 86, 114, 142, 170)
    );

    // NUmber of extra pad bits we need in order to completely create the
    // square symbol matrix
    private $iRemainderBits = array(
    1 => 0,
    2 => 7,
    3 => 7,
    4 => 7,
    5 => 7,
    6 => 7,
    7 => 0,
    8 => 0,
    9 => 0,
    10 => 0,
    11 => 0,
    12 => 0,
    13 => 0,
    14 => 3,
    15 => 3,
    16 => 3,
    17 => 3,
    18 => 3,
    19 => 3,
    20 => 3,
    21 => 4,
    22 => 4,
    23 => 4,
    24 => 4,
    25 => 4,
    26 => 4,
    27 => 4,
    28 => 3,
    29 => 3,
    30 => 3,
    31 => 3,
    32 => 3,
    33 => 3,
    34 => 3,
    35 => 0,
    36 => 0,
    37 => 0,
    38 => 0,
    39 => 0,
    40 => 0,
    );

    // Precomputed BCH (15,5) for format information,
    // This table has precomputed the remainder of dividing
    // the data with the Generator polynomial G(x) = x10 + x8 + x5 + x4 + x2 + x + 1
    //
    // The format of the table is
    // (DATA BITS), (REMAINDER), (DATA+REMAINDER)
    private $iBCH155 = array(
 '00000', '000000000000000', '000000000000000',
 '00001', '000000100110111', '000010100110111',
 '00010', '000001001101110', '000101001101110',
 '00011', '000001101011001', '000111101011001',
 '00100', '000000111101011', '001000111101011',
 '00101', '000000011011100', '001010011011100',
 '00110', '000001110000101', '001101110000101',
 '00111', '000001010110010', '001111010110010',
 '01000', '000001111010110', '010001111010110',
 '01001', '000001011100001', '010011011100001',
 '01010', '000000110111000', '010100110111000',
 '01011', '000000010001111', '010110010001111',
 '01100', '000001000111101', '011001000111101',
 '01101', '000001100001010', '011011100001010',
 '01110', '000000001010011', '011100001010011',
 '01111', '000000101100100', '011110101100100',
 '10000', '000001010011011', '100001010011011',
 '10001', '000001110101100', '100011110101100',
 '10010', '000000011110101', '100100011110101',
 '10011', '000000111000010', '100110111000010',
 '10100', '000001101110000', '101001101110000',
 '10101', '000001001000111', '101011001000111',
 '10110', '000000100011110', '101100100011110',
 '10111', '000000000101001', '101110000101001',
 '11000', '000000101001101', '110000101001101',
 '11001', '000000001111010', '110010001111010',
 '11010', '000001100100011', '110101100100011',
 '11011', '000001000010100', '110111000010100',
 '11100', '000000010100110', '111000010100110',
 '11101', '000000110010001', '111010110010001',
 '11110', '000001011001000', '111101011001000',
 '11111', '000001111111111', '111111111111111',
    );


    // Precomputed BCH(18,6) for version bit strings
    // (only used for versions 7 and higher)
    // This table has precomputed the remainder of dividing
    // the data with the Generator polynomial G(x) = x12 + x11 + x10 + x9 + x8 + x5 + x2 + 1
    //
    // The format of the table is
    // (DATA BITS), (REMAINDER BITS), (DATA+REMAINDER)
    private $iBCH186 = array(
 '000111', '000000110010010100', '000111110010010100',
 '001000', '000000010110111100', '001000010110111100',
 '001001', '000000101010011001', '001001101010011001',
 '001010', '000000010011010011', '001010010011010011',
 '001011', '000000101111110110', '001011101111110110',
 '001100', '000000011101100010', '001100011101100010',
 '001101', '000000100001000111', '001101100001000111',
 '001110', '000000011000001101', '001110011000001101',
 '001111', '000000100100101000', '001111100100101000',
 '010000', '000000101101111000', '010000101101111000',
 '010001', '000000010001011101', '010001010001011101',
 '010010', '000000101000010111', '010010101000010111',
 '010011', '000000010100110010', '010011010100110010',
 '010100', '000000100110100110', '010100100110100110',
 '010101', '000000011010000011', '010101011010000011',
 '010110', '000000100011001001', '010110100011001001',
 '010111', '000000011111101100', '010111011111101100',
 '011000', '000000111011000100', '011000111011000100',
 '011001', '000000000111100001', '011001000111100001',
 '011010', '000000111110101011', '011010111110101011',
 '011011', '000000000010001110', '011011000010001110',
 '011100', '000000110000011010', '011100110000011010',
 '011101', '000000001100111111', '011101001100111111',
 '011110', '000000110101110101', '011110110101110101',
 '011111', '000000001001010000', '011111001001010000',
 '100000', '000000100111010101', '100000100111010101',
 '100001', '000000011011110000', '100001011011110000',
 '100010', '000000100010111010', '100010100010111010',
 '100011', '000000011110011111', '100011011110011111',
 '100100', '000000101100001011', '100100101100001011',
 '100101', '000000010000101110', '100101010000101110',
 '100110', '000000101001100100', '100110101001100100',
 '100111', '000000010101000001', '100111010101000001',
 '101000', '000000110001101001', '101000110001101001'
 );


 private static $iInstance;
  
 private function __construct() {
     // $this->tblChk();
     // return $this->_instance=$this;
 }

 public static function getInstance() {
     if( !isset(self::$iInstance) ) {
         $c = __CLASS__;
         self::$iInstance = new $c();
     }
     return self::$iInstance;
 }


 function getFormatBits($aFormat) {
     if( $aFormat < 0 || $aFormat > 31 ) {
         //throw new QRException('Was expecting a format in range 0 <= f <= 31');
         throw new QRExceptionL(1300,$aFormat);
     }
     return $this->iBCH155[$aFormat*3+2];
 }

 function getVersionBits($aVersion) {
     if( $aVersion < 7 || $aVersion > 40 ) {
         //throw new QRException('Was expecting a version in range 7 <= f <= 40');
         throw new QRExceptionL(1301,$aVersion);
     }
     return $this->iBCH186[($aVersion-7)*3+2];
 }

 /*
  function tblChk() {
  $n = count($this->iQRStructure);
  for($i = 0; $i < $n; ++$i) {
   
  $nn = count($this->iQRStructure[$i]);
  $dc = $this->iQRStructure[$i][2] - $this->iQRStructure[$i][3];
   
  if( $dc != $this->iDataCapacity[$i] ) {
  throw new QRException('Index $i: Data capacity table does not match structure table.');
  }

  if($nn == 6) {
  $tot = $this->iQRStructure[$i][5][0] * $this->iQRStructure[$i][4];
  if($tot != $this->iQRStructure[$i][2]) {
  throw new QRException("Index $i : tot=$tot did not compute correctly!");
  }

  $ddc = $this->iQRStructure[$i][5][1] * $this->iQRStructure[$i][4];
  if($dc != $ddc) {
  throw new QRException("Index $i : dc=$dc != ddc=$ddc did not compute correctly");
  }
  }
  elseif($nn == 8) {
  $tot = $this->iQRStructure[$i][6][0] * $this->iQRStructure[$i][4] + $this->iQRStructure[$i][7][0] * $this->iQRStructure[$i][5];
  if($tot != $this->iQRStructure[$i][2]) {
  throw new QRException("Index $i : tot=$tot did not compute correctly!");
  }

  $ddc = $this->iQRStructure[$i][6][1] * $this->iQRStructure[$i][4] + $this->iQRStructure[$i][7][1] * $this->iQRStructure[$i][5];
  if($dc != $ddc) {
  throw new QRException("Index $i : dc=$dc != ddc=$ddc did not compute correctly.");
  }
  }
  else {
  throw new QRException("Index $i : len=$nn (expected 6 or 8)");
  }
  }
  }
  */

 function _chkVerErr($aVersion, $aErrLevel) {
     if($aVersion < 1 || $aVersion > 40 || $aErrLevel < 0 || $aErrLevel > 3) {
         //throw new QRException('QRCapacity:: Was expecting version in range [1,40] and error level in range [0,3]', -1);
         throw new QRExceptionL(1302,$aVersion,$aErrLevel);
     }
 }
 // Return number of data words for specified version and error level
 function getNumData($aVersion, $aErrLevel) {
     $this->_chkVerErr($aVersion, $aErrLevel);
     return $this->iDataCapacity[($aVersion-1) * 4 + $aErrLevel];
 }

 // Return number of error words for specified version and error level
 function getNumErr($aVersion, $aErrLevel) {
     $this->_chkVerErr($aVersion, $aErrLevel);
     return $this->iQRStructure[($aVersion-1) * 4 + $aErrLevel][3];
 }

 function getBlockStructure($aVersion, $aErrLevel) {
     $this->_chkVerErr($aVersion, $aErrLevel);
     $d = $this->iQRStructure[($aVersion-1) * 4 + $aErrLevel];
     if(count($d) == 6) {
         // (Total nbr blocks, (total words in block, data words in block, error words in block))
         return array(array($d[4], array($d[5][0], $d[5][1], $d[5][0] - $d[5][1])));
     }
     else {
         // (Total nbr blocks, (total words in block, data words in block, error words in block))
         return array(array($d[4], array($d[6][0], $d[6][1], $d[6][0] - $d[6][1])),
         array($d[5], array($d[7][0], $d[7][1], $d[7][0] - $d[7][1]))
         );
     }
 }

 // Return a list of (x,y)-coordinates for the alignment
 // pattern positions
 function getAlignmentPositions($aVersion) {
     $len = $this->getDimension($aVersion);
     $p = $this->iAlignmentPatterns[$aVersion];

     // Now form all possible combincations
     $nbr = $p[0]; // This is the total number of alignment patterns
     $n = count($p)-1;
     $coord=array();
     for( $i=0; $i<$n; ++$i) {
         for( $j=0; $j<$n; ++$j) {
             $x=$p[$i+1];  // The first position is the number so we need to offset +1
             $y=$p[$j+1];

             // We need to check so that it doesn't collide with the
             // finder patterns
             if( ($x < 9 && $y < 9) || /* Upper left find pattern */
                 ($x>=$len-9 && $y < 9) ||   /* Upper right find pattern */
                 ($x < 9 && $y >= $len-9) /* Lower left find pattern */
                 ) {
                 // Nothing
             }
             else {
                 $coord[] = array($x,$y);
             }
         }
     }

     // Sanity check that the found number of patterns is as many as they should be
     $nn=count($coord);
     if( $nn != $nbr ) {
         //throw new QRException("Internal error: Sanity check in getAlignmentPositions() failed expected $nbr patterns but found $nn patterns (len=$len).");
         throw new QRExceptionL(1303,$nbr,$nn,$len);
     }

     return $coord;
 }

 // Get the number of pad bits we need to completely fill up the matrix for the
 // specified version
 function getRemainderBits($aVersion) {
     if($aVersion < 1 || $aVersion > 40) {
         //throw new QRException('QRCapacity:: Was expecting version in range [1,40]', -1);
         throw new QRExceptionL(1304,'getRemainderBits()',$aVersion);
     }
     return $this->iRemainderBits[$aVersion];
 }

 // Get the total number of codewoards (data+error correction) in a symbol of specified version
 function getTotalCodewords($aVersion) {
     if($aVersion < 1 || $aVersion > 40) {
         throw new QRExceptionL(1304,'getTotalCodewords()',$aVersion);
         //throw new QRException('QRCapacity:: Was expecting version in range [1,40]', -1);
     }
     return $this->iQRStructure[($aVersion-1)*4][2];
 }

  
 // QR code version 1 starts at 21x21 module square and each version adds 4 modules
 static function getDimension($aVersion) {
     if($aVersion < 1 || $aVersion > 40) {
         //throw new QRException('QRCapacity:: Was expecting version in range [1,40]', -1);
         throw new QRExceptionL(1304,'getDimension()',$aVersion);
     }
     $l = $aVersion * 4 + 17;
     return $aVersion * 4 + 17;
 }
}

?>
