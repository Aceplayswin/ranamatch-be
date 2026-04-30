<?php
error_reporting(0);
define('ACCESS_SECURITY', 'true');
include '../security/config.php';

$discovery_file = __DIR__ . "/discovered_lobby_games.json";

// Handle manual renaming
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename') {
    $uid = mysqli_real_escape_string($conn, $_POST['uid']);
    $new_name = mysqli_real_escape_string($conn, $_POST['new_name']);
    
    // Update DB
    mysqli_query($conn, "INSERT INTO tbl_game_names (tbl_game_id, tbl_game_name) VALUES ('$uid', '$new_name') ON DUPLICATE KEY UPDATE tbl_game_name = '$new_name'");
    
    // Update JSON file
    if (file_exists($discovery_file)) {
        $games = json_decode(file_get_contents($discovery_file), true) ?: [];
        foreach($games as &$g) {
            if ($g['uid'] === $_POST['uid']) {
                $g['name'] = $_POST['new_name'];
            }
        }
        file_put_contents($discovery_file, json_encode($games, JSON_PRETTY_PRINT));
    }
    echo "success";
    exit;
}

$games = [];
if (file_exists($discovery_file)) {
    $games = json_decode(file_get_contents($discovery_file), true) ?: [];
    
    // Enrich names from DB if they were renamed elsewhere
    $db_names = [];
    $res = mysqli_query($conn, "SELECT tbl_game_id, tbl_game_name FROM tbl_game_names");
    while($r = mysqli_fetch_assoc($res)) { $db_names[$r['tbl_game_id']] = $r['tbl_game_name']; }
    
    foreach($games as &$g) {
        if (isset($db_names[$g['uid']])) {
            $g['name'] = $db_names[$g['uid']];
        }
    }

    // Sort by newest first
    usort($games, function($a, $b) {
        return strtotime($b['discovered_at']) - strtotime($a['discovered_at']);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lobby Game Discovery Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Outfit:wght@800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .outfit { font-family: 'Outfit', sans-serif; }
    </style>
    <script>
        async function renameGame(uid, currentName) {
            const newName = prompt("Enter the correct name for this game:", currentName || "");
            if (newName && newName !== currentName) {
                const formData = new FormData();
                formData.append('action', 'rename');
                formData.append('uid', uid);
                formData.append('new_name', newName);
                
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                if (await res.text() === "success") {
                    window.location.reload();
                }
            }
        }
    </script>
</head>
<body class="p-4 md:p-10">
    <div class="max-w-6xl mx-auto">
        <header class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-4xl outfit font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-orange-500 uppercase tracking-tighter">
                    Lobby Discovery Hub
                </h1>
                <p class="text-slate-400 text-sm mt-2 font-medium">Automatically tracking games launched from within provider lobbies.</p>
            </div>
            <div class="flex items-center gap-3 bg-slate-800/50 px-4 py-2 rounded-2xl border border-slate-700">
                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-xs font-bold uppercase tracking-widest text-slate-300">Live Tracker Active</span>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($games)): ?>
                <div class="col-span-full py-20 text-center bg-slate-800/30 rounded-3xl border border-dashed border-slate-700">
                    <p class="text-slate-500 font-bold uppercase tracking-widest">No games discovered yet.</p>
                    <p class="text-slate-600 text-xs mt-2">Try playing a game inside the Ezugi or Evolution lobby to start tracking.</p>
                </div>
            <?php else: ?>
                <?php foreach($games as $game): ?>
                    <div class="bg-slate-800/40 backdrop-blur-md border border-slate-700 p-6 rounded-3xl hover:border-amber-500/50 transition-all duration-300 group shadow-xl">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-[10px] bg-amber-500/20 text-amber-500 px-3 py-1 rounded-full font-black uppercase tracking-widest">
                                Discovered
                            </span>
                            <span class="text-[10px] text-slate-500 font-medium italic">
                                <?php echo date('M d, H:i', strtotime($game['discovered_at'])); ?>
                            </span>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1 group-hover:text-amber-400 transition-colors">
                            <?php echo htmlspecialchars($game['name']); ?>
                        </h3>
                        <p class="text-[10px] font-mono text-slate-500 break-all mb-4">
                            UID: <?php echo htmlspecialchars($game['uid']); ?>
                        </p>
                        
                        <div class="pt-4 border-t border-slate-700/50 flex gap-2">
                            <button onclick='renameGame("<?php echo $game['uid']; ?>", "<?php echo addslashes($game['name']); ?>")' class="flex-1 py-2 bg-amber-500/10 hover:bg-amber-500 text-amber-500 hover:text-black rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                                Rename
                            </button>
                            <a href="https://www.google.com/search?q=<?php echo urlencode($game['uid']); ?>+game+name" target="_blank" class="px-4 py-2 bg-blue-500/10 hover:bg-blue-500 text-blue-500 hover:text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center">
                                Search
                            </a>
                            <button onclick='alert(<?php echo json_encode(json_encode($game['raw_data'], JSON_PRETTY_PRINT)); ?>)' class="px-4 py-2 bg-slate-700/50 hover:bg-slate-700 text-slate-400 hover:text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                                Info
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <footer class="mt-20 text-center opacity-20 hover:opacity-100 transition-opacity duration-500">
            <p class="text-[10px] font-black uppercase tracking-[0.5em]">Winco Discovery System v2.0</p>
        </footer>
    </div>
</body>
</html>
