#!/usr/bin/perl -P

use strict;
my $sessiondir = "/lib/init/rw";
my $sessionname = "SESSIONID_VULN_SITE";

use CGI;
use CGI::Carp qw ( fatalsToBrowser ); 
my $cgi  = new CGI;
my $sid  = $cgi->cookie($sessionname);
my $file = $cgi->param('file');
my $filename = "$file";
my $sessionfile = $sessiondir."/sess_".$sid."_uploadto";

# debugging lines
#print $cgi->header();

$filename =~ s/://g;
$filename =~ s/;//g;
$filename =~ s/\?//g;
$filename =~ s/"//g;
$filename =~ s/'//g;
$filename =~ s/|//g;
$filename =~ s/\>//g;
$filename =~ s/\<//g;
$filename =~ s/\*//g;

#print "origfile: $origfile<br>\n\r";
#print "file: $file<br>\n\r";

unless (-e "$sessionfile") {
	print $cgi->header();
	print "Invalid Session.\n";
	print "Session ID: $sid\n\r";
	#print "sessionfile: $sessionfile<br>\n\r";
	exit;
}

open SESSFILE, "<$sessionfile";
my $uploaddir = do { local $/; <SESSFILE> };
my $fullpath = "$uploaddir/$filename";

unless (-e "$uploaddir") {
	print $cgi->header();
	print "Invalid Session.\n";
	print "Session ID: $sid\n\r";
	#print "uploaddir: $uploaddir\n\r";
	exit;
}

# debugging lines
#print $cgi->header();
#print "sessionname: $sessionname<br>\n\r";
#print "sessionfile: $sessionfile<br>\n\r";
#print "sid: $sid<br>\n\r";
#print "sid2: $sid2<br>\n\r";
#print "uploaddir: $uploaddir<br>\n\r";
#print "filename: $filename<br>\n\r";
#print "fullpath: $fullpath<br>\n\r";

my $counter = 0;
open(LOCAL, ">$fullpath") or die $!;
while(<$file>) {
    print LOCAL $_;
   last if (++$counter > 10000);
}

#print "Status: 302 Moved\nLocation: upload.php\n\n";
print "Status: 302 Moved\nLocation: /upload/download.php?image=\nX-Created-Filename: $fullpath\n\n";
