import { bootstrapApplication } from '@angular/platform-browser';
import { Component, AfterViewInit, provideZonelessChangeDetection } from '@angular/core';
import { loadExistingUi } from '../../../libs/compartido/src/legacy-adapter';

/** Angular shell reusing the stable Sector Sign UI during phased migration. */
@Component({selector:'sector-receptor-app',standalone:true,templateUrl:'./app.html'})
class SectorSignRoot implements AfterViewInit {
  async ngAfterViewInit():Promise<void> {
    await loadExistingUi('/assets/scheduler.js?v=070');
    await loadExistingUi('/assets/player.js?v=070');
  }
}
bootstrapApplication(SectorSignRoot,{providers:[provideZonelessChangeDetection()]})
  .catch(error=>console.error('Sector Sign startup error:',error));
