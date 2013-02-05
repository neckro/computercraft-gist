-- gist script by neckro 2013.  WTFPL.
-- some (lots) of this code is borrowed from the 'pastebin' script.

-- proxy_url must be set!  See README.
proxy_url = ""

-- todo: make sure this fits on the screen
local function printUsage()
	print( "Retrieves code from gist.github.com.  Usage:" )
	print( "gist put <filename>" )
	print( "gist get <gist id>[/commit] [filename]" )
	print( "gist update <filename>" )
	print( "Commands can be one letter (p, g, u)." )
	print( "[filename] and [commit] are optional for 'get'." )
end

if #proxy_url == 0 then
	print ( "Error: Proxy URL is not set!" )
	print ( "You must edit this script to set the proxy." )
	return
end

if not http then
	print( "Gist requires http API." )
	print( "Set enableAPI_http to 1 in mod_ComputerCraft.cfg." )
	return
end

local tArgs = { ... }
local sCommand = tArgs[1]

if #tArgs < 2 then
	printUsage()
	return
end

if ( sCommand == "put" )
or ( sCommand == "p" ) then
	-- Upload a file to gist via proxy
	-- Determine file to upload
	local sFile = tArgs[2]
	local sPath = shell.resolve( sFile )
	if not fs.exists( sPath )
	or fs.isDir( sPath ) then
		print( "No such file: "..sPath )
		return
	end

	-- Read in the file
	local sName = fs.getName( sPath )
	local file = fs.open( sPath, "r" )
	local sText = file.readAll()
	file.close()

	-- POST the contents to gist via proxy
	write( "Connecting to proxy... " )
	local response = http.post(
		proxy_url,
		"filename="..textutils.urlEncode(sName)
		.."&"..
		"data="..textutils.urlEncode(sText)
		)

	if ( response == nil ) then
		print ( "Request failed." )
		return
	end

	print ( "Success." )
	local git_id = response.readLine()
	local fork_id = response.readLine()
	response.close()

	print( "Uploaded gist id "..git_id )
	if ( fork_id ~= nil ) then
		print( "Forked from "..fork_id )
	end

elseif ( sCommand == "get" )
or     ( sCommand == "g" ) then
	-- Download a file from gist proxy
	if #tArgs < 2 then
		printUsage()
		return
	end

	-- Determine file to download
	local sCode = tArgs[2]
	local sFile = tArgs[3]
	local sPath

	if ( sFile ~= nil ) then
		sPath = shell.resolve( sFile )
		if fs.exists( sPath ) then
			print( "File already exists" )
			return
		end
	end

	-- GET the contents from Gist proxy
	write( "Connecting to proxy... " )
	local response = http.get(
		proxy_url.."?gist="..textutils.urlEncode( sCode )
	)

	if response then
		print( "Success." )

		local rFile = response.readLine()
		local sResponse = response.readAll()
		response.close()

		if sFile == nil then
			-- use remote filename if not specified
			sPath = shell.resolve( rFile )
			if fs.exists( sPath ) then
				print( "File already exists: "..sPath )
				return
			end
		end

		local file = fs.open( sPath, "w" )
		file.write( sResponse )
		file.close()

		print( "Downloaded to "..sPath )
	else
		print( "Failed." )
	end

elseif ( sCommand == "update" )
or     ( sCommand == "u" ) then
	if #tArgs < 2 then
		printUsage()
		return
	end

	local sFile = tArgs[2]
	local sPath = shell.resolve( sFile )

	if ( not fs.exists( sPath ) ) then
		print ( "File does not exist: " .. sPath )
		return
	end

	local file = fs.open( sPath, "r" )
	local line = file.readLine()
	file.close()

	local indicator = "-- https://gist.github.com/"

	if ( string.sub( line, 0, string.len( indicator )) ~= indicator ) then
		print ( "File is not a gist" )
		return
	end

	local info = string.sub( line, string.len( indicator ) + 1 )
	-- strip commit number so we get the HEAD commit
	info = string.gsub( info, "/[a-f0-9]*$", "" )

	write( "Connecting to proxy... " )
	local response = http.get(
		proxy_url.."?gist="..textutils.urlEncode( info )
	)

	if response then
		print( "Success." )

		-- skip the filename, we already know it
		response.readLine()
		local sResponse = response.readAll()
		response.close()
		file = fs.open( sPath, "w" )
		file.write( sResponse )
		file.close()
		print( "Updated." )
	else
		print( "Failed!" )
	end

else
	printUsage()
	return
end
