document.addEventListener("DOMContentLoaded", function() {
    const nowPlayingElement = document.getElementById('azura-now-playing');
    const nextTrackElement = document.getElementById('azura-next-track');

    // Fetch plugin settings dynamically
    const azuraCastUrl = azuracastSettings.url;  // Assumes azuracastSettings is localized from PHP
    const stationName = azuracastSettings.station_name;

    function connectWebSocket() {
        const ws = new WebSocket(`${azuraCastUrl}/api/live/nowplaying/websocket`);

        ws.onopen = () => {
            console.log("WebSocket connection established");
            ws.send(JSON.stringify({ 'subs': { [`station:${stationName}`]: { 'recover': true } } }));
        };

        ws.onmessage = (event) => {
            console.log("WebSocket message received:", event.data);
            const data = JSON.parse(event.data);

            const song = data.now_playing.song.artist + ' - ' + data.now_playing.song.title;
            nowPlayingElement.innerText = 'Now Playing: ' + song;

            if (data.playing_next && data.playing_next.song) {
                const nextSong = data.playing_next.song.artist + ' - ' + data.playing_next.song.title;
                if (nextTrackElement) {
                    nextTrackElement.innerText = 'Next Track: ' + nextSong;
                }
            } else {
                console.warn("No next track data available");
            }
        };

        ws.onerror = (error) => {
            console.error("WebSocket error:", error);
            nowPlayingElement.innerText = 'Error connecting to AzuraCast.';
        };

        ws.onclose = () => {
            console.warn("WebSocket connection closed. Attempting to reconnect in 5 seconds...");
            setTimeout(connectWebSocket, 5000);  // Auto-reconnect after 5 seconds
        };
    }

    if (nowPlayingElement) {
        connectWebSocket();
    }
});