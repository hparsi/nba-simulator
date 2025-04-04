// Web Worker for simulation timing
let intervalId = null;
let intervalTime = 5000; // Default to 5 seconds

self.addEventListener('message', function(e) {
    const data = e.data;
    
    switch (data.command) {
        case 'start':
            if (data.interval) {
                intervalTime = data.interval;
            }
            
            if (intervalId) {
                clearInterval(intervalId);
            }
            
            intervalId = setInterval(function() {
                self.postMessage({ type: 'tick' });
            }, intervalTime);
            
            self.postMessage({ type: 'started', interval: intervalTime });
            break;
            
        case 'stop':
            if (intervalId) {
                clearInterval(intervalId);
                intervalId = null;
                self.postMessage({ type: 'stopped' });
            }
            break;
            
        default:
            self.postMessage({ type: 'error', message: 'Unknown command: ' + data.command });
    }
});

self.addEventListener('close', function() {
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }
}); 