#!/usr/bin/env python3
"""Consistency checks; no network and no user data required."""
from pathlib import Path
import json,re,sys
root=Path(__file__).resolve().parent.parent
release=json.loads((root/'server/private/release.json').read_text(encoding='utf-8'))
v=release['version'];code=int(release['android_version_code'])
gradle=(root/'android-unificado/app/build.gradle').read_text()
angular=json.loads((root/'angular-unificado/package.json').read_text())
app=(root/'server/public/assets/app.js').read_text()
player=(root/'server/public/assets/player.js').read_text()
index=(root/'server/public/index.php').read_text()
receptor=(root/'server/public/player.php').read_text()
sw=(root/'server/public/sw-player.js').read_text()
workflow=(root/'.github/workflows/build-two-android-apps.yml').read_text()
checks={
 'Release JSON':re.fullmatch(r'\d+\.\d+\.\d+',v)!=None,
 'Angular version':angular['version']==v,
 'Android versionName':f"versionName '{v}'" in gradle,
 'Android versionCode':f'versionCode {code}' in gradle,
 'Build timestamp':str(release['released_at']) in gradle,
 'Centro JS client release':f"version:'{v}'" in app,
 'Receptor JS client release':f"version:'{v}'" in player,
 'PHP centro metadata':"window.SS_BOOT_RELEASE" in index,
 'PHP receptor metadata':"window.SS_BOOT_RELEASE" in receptor,
 'Heartbeat throttle': 'INTERVAL 15 SECOND' in (root/'server/private/player-api.php').read_text(),
 'Player 5s adaptive sync': 'scheduleSync(lastSync?5000:' in player,
 'Center 8s near-realtime': 'scheduleLivePoll' in app,
 'SW versioned shell':'sector-sign-player-shell-v070' in sw,
 'Android two apps':all(x in gradle for x in ['centro {','receptor {','com.sectorsign.centro','com.sectorsign.receptor']),
 'GitHub two APK artifacts':all(x in workflow for x in ['assembleCentroDebug','assembleReceptorDebug','upload-artifact']),
}
for name,ok in checks.items():print(('PASS' if ok else 'FAIL')+' '+name)
if not all(checks.values()):sys.exit(1)
