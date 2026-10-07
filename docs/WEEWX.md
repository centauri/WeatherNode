# Connect more stations with WeeWX

Is your weather station not on the WeatherNode list? WeeWX can probably connect it.

[WeeWX](https://weewx.com) is free software that runs on a small computer next to your station, often a Raspberry Pi. It reads your station over USB, serial or radio. It works with more than 70 station models from brands like AcuRite, Davis, Fine Offset, LaCrosse, Oregon Scientific, Peet Bros, RainWise, Hideki and Vaisala. See the [full list](https://weewx.com/hardware.html).

WeeWX writes your live readings to a small file called `realtime.txt`. WeatherNode reads that file every minute.

## What you need

- WeeWX 5, installed and getting data from your station
- The WeeWX **crt** extension, which writes `realtime.txt`
- A way for WeatherNode to read the file (step 3)

## Step 1: Install WeeWX

Follow the quick start for your system in the [WeeWX docs](https://weewx.com/docs). Make sure WeeWX shows readings from your station before you go on.

## Step 2: Install the crt extension

Run this on the computer that runs WeeWX:

```sh
weectl extension install https://github.com/matthewwall/weewx-crt/archive/master.zip
```

If you installed WeeWX with `apt`, put `sudo` in front.

## Step 3: Choose where the file goes

You set the file location in `weewx.conf`. You can find it here:

- Installed with `apt`: `/etc/weewx/weewx.conf`
- Installed with `pip`: `~/weewx-data/weewx.conf`

Look for the `[CumulusRealTime]` section. Pick the option that fits your setup.

### A. WeatherNode runs on the same computer

Keep the default:

```ini
[CumulusRealTime]
    filename = /var/tmp/realtime.txt
```

In WeatherNode, the file path is `/var/tmp/realtime.txt`.

### B. WeatherNode runs on another computer in your home

Write the file into the WeeWX web folder:

```ini
[CumulusRealTime]
    filename = /var/www/html/weewx/realtime.txt
```

The computer running WeeWX needs a web server for this, such as nginx (`sudo apt install nginx`). See [WeeWX web server setup](https://weewx.com/docs/5.2/usersguide/webserver/).

In WeatherNode, the file path is `http://<your-pi>/weewx/realtime.txt`. Replace `<your-pi>` with the name or IP address of the computer running WeeWX, for example `http://raspberrypi.local/weewx/realtime.txt`.

### C. WeatherNode runs online (shared hosting or a server)

An online WeatherNode can't see inside your home network. Let WeeWX upload the file to your website instead.

1. Write the file into the WeeWX web folder, the same way as option B.
2. Turn on the FTP or RSYNC upload in `weewx.conf` (in `[StdReport]`). WeeWX then uploads changed files from that folder to your website.
3. In WeatherNode, use the web address of the uploaded file, for example `https://example.com/weewx/realtime.txt`.

WeeWX uploads once every archive period, which is 5 minutes by default. So your live readings update every 5 minutes, not every minute.

### Restart and check

Restart WeeWX:

```sh
sudo systemctl restart weewx
```

Open the file, or the web address in your browser. You should see one line of numbers that changes every few seconds.

## Step 4: Set up WeatherNode

1. In the admin panel, go to **Live Data Source**.
2. Set **Primary Live Data Source** to **WeeWX**.
3. Set **Fetch Mode** to **Local file**.
4. Fill in **File Path** with the path or web address from step 3.
5. Click **Save Changes**.
6. Click **Test Connection**. It tests the saved settings, so save first.

![Live Data Source page with WeeWX picked and a web address as the file path](../screenshots/admin-livedata-weewx.webp)

You don't need to set any units. WeatherNode reads the units from the file and converts them for you.

## If something is wrong

**Many values are missing.** Some station drivers send only part of the readings each time. Add this to `[CumulusRealTime]` and restart WeeWX:

```ini
    binding = archive
```

The file then updates once every archive period instead of every few seconds.

**Average wind, gust or wind direction is missing.** WeeWX needs an archive period of 5 minutes or less to work these out. In `[StdArchive]`, set `archive_interval = 300`.

**There is no file.** The user that runs WeeWX must be allowed to write to the folder. Check the WeeWX log for errors from `crt`.

**Test Connection fails with a web address.** Open the address in a browser on the computer that runs WeatherNode. If it doesn't load there, WeatherNode can't load it either.
