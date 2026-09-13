<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>Editor de certificados · SICAD</title>
  <!-- Mantém a versão usada pelo projeto. Não é necessário npm ou compilação. -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
  <style>
    :root {
      --bg: #f4f6fa;
      --surface: #fff;
      --ink: #182238;
      --muted: #606f85;
      --line: #e5eaf2;
      --accent: #4f46e5;
      --accent-soft: #eeedff;
      --navy: #182b50;
      --danger: #b42337;
      --radius: 14px;
      --shadow: 0 8px 32px rgba(23, 37, 67, .05)
    }

    * {
      box-sizing: border-box
    }

    [hidden] {
      display: none !important
    }

    body {
      margin: 0;
      background: var(--bg);
      color: var(--ink);
      font: 14px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif
    }

    button,
    input,
    select {
      font: inherit
    }

    button {
      cursor: pointer
    }

    button,
    select,
    input {
      outline-offset: 3px
    }

    button:focus-visible,
    input:focus-visible,
    select:focus-visible,
    summary:focus-visible {
      outline: 3px solid #a9a3ff
    }

    button:disabled,
    input:disabled,
    select:disabled {
      cursor: not-allowed;
      opacity: .45
    }

    button {
      transition: background .15s, border-color .15s, box-shadow .15s
    }

    .icon {
      width: 18px;
      height: 18px;
      flex: none;
      fill: none;
      stroke: currentColor;
      stroke-width: 1.8;
      stroke-linecap: round;
      stroke-linejoin: round;
      vertical-align: middle
    }

    .topbar {
      min-height: 78px;
      display: flex;
      align-items: center;
      gap: 26px;
      padding: 14px 26px;
      background: var(--surface);
      border-bottom: 1px solid var(--line)
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      flex: none
    }

    .brand-mark {
      display: grid;
      place-items: center;
      width: 38px;
      height: 40px;
      background: var(--navy);
      color: #fff;
      border-radius: 11px
    }

    .brand-mark .icon {
      width: 24px;
      height: 24px
    }

    .brand strong {
      display: block;
      font-size: 17px;
      letter-spacing: 1.3px
    }

    .brand small {
      display: block;
      font-size: 10px;
      color: var(--muted);
      letter-spacing: 1.2px;
      text-transform: uppercase
    }

    .header-copy {
      border-left: 1px solid var(--line);
      padding-left: 26px;
      min-width: 0
    }

    .breadcrumb {
      font-size: 11px;
      color: var(--muted);
      margin: 0 0 2px
    }

    .breadcrumb span {
      padding: 0 7px;
      color: #a1aabd
    }

    h1 {
      font-size: 18px;
      letter-spacing: -.35px;
      line-height: 1.3;
      margin: 0;
      font-weight: 650
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-left: auto;
      flex-wrap: wrap
    }

    .save-state {
      font-size: 12px;
      color: var(--muted);
      display: flex;
      align-items: center;
      gap: 6px;
      margin-right: 8px
    }

    .dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #8994a7
    }

    .save-state[data-state=dirty] .dot {
      background: #d89b2b
    }

    .save-state[data-state=saved] .dot {
      background: #16a076
    }

    .save-state[data-state=error] .dot {
      background: var(--danger)
    }

    .btn {
      min-height: 38px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      border: 1px solid var(--line);
      border-radius: 9px;
      background: #fff;
      color: var(--ink);
      padding: 8px 13px;
      font-weight: 550;
      text-decoration: none;
      white-space: nowrap;
      font-size: 12px
    }

    .btn:hover:not(:disabled) {
      background: #f6f7fb;
      border-color: #c7cede
    }

    .btn.primary {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
      box-shadow: 0 3px 8px #4f46e51a
    }

    .btn.primary:hover:not(:disabled) {
      background: #4037cc
    }

    .btn.soft {
      background: var(--accent-soft);
      border-color: var(--accent-soft);
      color: #4940ce
    }

    .btn.soft:hover:not(:disabled) {
      background: #e2dfff
    }

    .btn.ghost {
      background: transparent;
      border-color: transparent
    }

    .btn.danger {
      color: var(--danger)
    }

    .btn.danger:hover:not(:disabled) {
      background: #fff1f3;
      border-color: #f5c2cc
    }

    .btn.full {
      width: 100%
    }

    .btn.icon-btn {
      width: 34px;
      min-height: 34px;
      padding: 6px
    }

    .btn.is-active {
      background: var(--accent-soft);
      color: var(--accent);
      border-color: #d6d2ff
    }

    .editor-grid {
      display: grid;
      grid-template-columns: 236px minmax(0, 1fr) 246px;
      gap: 18px;
      padding: 22px 24px;
      max-width: 1900px;
      margin: 0 auto;
      align-items: start
    }

    .panel {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      min-width: 0;
      overflow: hidden
    }

    .panel-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 17px 13px
    }

    .panel-head h2 {
      font-size: 14px;
      margin: 0;
      letter-spacing: -.2px
    }

    .overline {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 1.25px;
      font-weight: 650;
      color: var(--muted)
    }

    .section {
      padding: 17px;
      border-top: 1px solid var(--line)
    }

    .section h3 {
      margin: 0 0 12px;
      font-size: 11px;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: var(--muted);
      font-weight: 650
    }

    .section p {
      margin: 8px 0 0
    }

    .stack {
      display: grid;
      gap: 9px
    }

    .hint {
      font-size: 11px;
      line-height: 1.65;
      color: var(--muted)
    }

    .tiny {
      font-size: 10px;
      color: var(--muted)
    }

    .tabs {
      display: flex;
      padding: 5px;
      background: #f4f5f9;
      border-radius: 10px;
      margin: 0 13px 13px;
      gap: 3px
    }

    .tab {
      flex: 1;
      border: 0;
      background: transparent;
      border-radius: 7px;
      padding: 8px 5px;
      font-size: 12px;
      font-weight: 600;
      color: var(--muted)
    }

    .tab[aria-selected=true] {
      background: #fff;
      color: var(--ink);
      box-shadow: 0 2px 6px #19253f0b
    }

    .tag-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 7px
    }

    .tag-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 0;
      min-height: 40px;
      border: 1px solid #dfdcef;
      border-radius: 9px;
      color: #48408c;
      background: #f8f7fd;
      padding: 8px 5px;
      font-size: 11px;
      font-weight: 550;
      line-height: 1.35;
      text-align: center
    }

    .tag-btn:hover {
      background: #eeebff;
      border-color: #c8c1f5
    }


    .check-row {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      font-size: 11px;
      line-height: 1.5;
      color: var(--muted);
      margin-top: 10px
    }

    .check-row input {
      margin: 2px 0 0;
      accent-color: var(--accent);
      flex: none
    }

    .check-row span {
      flex: 1
    }

    .field {
      display: grid;
      gap: 5px;
      min-width: 0
    }

    .field>span,
    .field>label {
      font-size: 11px;
      color: var(--muted)
    }

    input[type=number],
    select {
      min-height: 35px;
      width: 100%;
      min-width: 0;
      border: 1px solid #dfe4ed;
      border-radius: 7px;
      background: #fff;
      color: var(--ink);
      padding: 7px 8px;
      font-size: 12px
    }

    input[type=color] {
      width: 35px;
      height: 34px;
      background: #fff;
      border: 1px solid #dfe4ed;
      padding: 4px;
      border-radius: 7px;
      cursor: pointer
    }

    input[type=color]::-webkit-color-swatch {
      border: 0;
      border-radius: 4px
    }

    .unit-input {
      position: relative
    }

    .unit-input input {
      padding-right: 27px
    }

    .unit-input>span {
      position: absolute;
      right: 9px;
      top: 9px;
      color: #9aa3b4;
      font-size: 10px;
      pointer-events: none
    }

    .two-cols {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px
    }

    .shape-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 7px
    }

    .shape-grid .btn {
      display: flex;
      flex-direction: column;
      padding: 9px 4px;
      font-size: 10px;
      gap: 6px;
      min-height: 60px
    }

    .line-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 7px;
      margin-top: 8px
    }

    .line-grid .btn {
      font-size: 10px;
      padding: 7px
    }

    .inline-row {
      display: flex;
      align-items: center;
      gap: 9px;
      min-width: 0
    }

    .details-block {
      border-top: 1px solid var(--line)
    }

    .details-block>summary {
      list-style: none;
      cursor: pointer;
      padding: 15px 17px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      font-weight: 600
    }

    .details-block>summary::-webkit-details-marker {
      display: none
    }

    .details-block>summary:after {
      content: '+';
      font-size: 16px;
      font-weight: 400;
      color: #8b96a8
    }

    .details-block[open]>summary:after {
      content: '−'
    }

    .details-body {
      padding: 0 17px 17px
    }

    .workspace {
      min-width: 0
    }

    .workspace-label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 13px;
      gap: 10px
    }

    .workspace-label>div {
      display: flex;
      align-items: center;
      gap: 9px
    }

    .badge {
      font-size: 10px;
      font-weight: 600;
      border-radius: 5px;
      padding: 3px 7px;
      background: #e9edf5;
      color: #687791;
      white-space: nowrap
    }

    .badge.accent {
      background: var(--accent-soft);
      color: #5a50c5
    }

    .toolbar {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 6px;
      padding: 10px 11px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 12px 12px 0 0
    }

    .toolbar .font-field {
      width: 130px
    }

    .toolbar .size-field {
      width: 60px
    }


    .toolbar label {
      display: block
    }

    .divider {
      width: 1px;
      height: 24px;
      background: var(--line);
      margin: 0 4px
    }

    .style-btn {
      font: 700 14px Georgia, serif
    }

    .style-btn.italic {
      font-style: italic
    }

    .style-btn.underline {
      text-decoration: underline
    }

    .board {
      position: relative;
      min-height: 430px;
      background: #eaf0f7;
      background-image: radial-gradient(#cdd6e4 .8px, transparent .8px);
      background-size: 18px 18px;
      border: 1px solid var(--line);
      border-top: 0;
      border-bottom: 0;
      overflow: auto;
      max-height: calc(100dvh - 267px);
      padding: 36px 26px;
      display: flex;
      align-items: flex-start
    }

    .paper-holder {
      margin: auto;
      position: relative;
      flex: none
    }

    #canvasStage {
      position: relative;
      background: #fff;
      box-shadow: 0 5px 10px #1427440b, 0 20px 40px #1427441f;
      line-height: 0
    }

    canvas {
      display: block
    }

    #safeOverlay {
      position: absolute;
      inset: 0;
      pointer-events: none;
      width: 100%;
      height: 100%;
      z-index: 2
    }

    #safeRect {
      stroke: #857bd5;
      stroke-width: 1;
      stroke-dasharray: 6 5;
      opacity: .7
    }

    #canvasStage>.canvas-container {
      max-width: none
    }

    .empty-paper {
      position: absolute;
      inset: 0;
      z-index: 3;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      gap: 13px;
      pointer-events: none;
      padding: 24px;
      line-height: 1.5;
      text-align: center
    }

    .empty-paper .icon {
      width: 42px;
      height: 42px;
      color: #b6bfd0
    }

    .empty-paper strong {
      font-size: clamp(14px, 1.3vw, 19px);
      letter-spacing: -.3px;
      font-weight: 600
    }

    .empty-paper p {
      max-width: 265px;
      font-size: 12px;
      color: var(--muted);
      margin: 0
    }

    .empty-paper button {
      pointer-events: auto
    }

    .workspace-foot {
      padding: 10px 13px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 9px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 0 0 12px 12px;
      font-size: 11px;
      color: var(--muted)
    }

    .zoom-controls {
      display: flex;
      align-items: center;
      gap: 4px
    }

    .zoom-controls select {
      width: 91px;
      min-height: 29px;
      padding: 4px 5px;
      border-color: transparent;
      font-size: 11px
    }

    .zoom-controls .btn {
      min-height: 29px;
      width: 27px
    }

    .below-board {
      font-size: 11px;
      color: #8a94a7;
      text-align: center;
      margin: 14px 0
    }

    .below-board kbd {
      font: 10px inherit;
      border: 1px solid #d9e0ea;
      border-bottom-width: 2px;
      padding: 1px 4px;
      border-radius: 4px;
      background: #fff;
      color: #76849a
    }

    .warning {
      padding: 11px 13px;
      background: #fff8e9;
      color: #865318;
      border: 1px solid #f2dbab;
      border-radius: 9px;
      margin: 0 0 12px;
      font-size: 12px;
      white-space: pre-line
    }

    .property-empty {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      padding: 18px 18px 22px;
      color: var(--muted);
      gap: 9px;
      font-size: 12px
    }

    .property-empty .icon {
      width: 27px;
      height: 27px;
      color: #a2acbc
    }

    .property-empty p {
      margin: 0;
      font-size: 11px;
      line-height: 1.6
    }

    .property-tag {
      font-size: 10px;
      color: #7167be;
      border: 1px solid #e4def9;
      background: #faf8ff;
      border-radius: 6px;
      padding: 4px 7px;
      max-width: 100%;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis
    }

    .property-title {
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px
    }

    .property-title strong {
      font-size: 12px
    }

    .flow-note {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      background: #f4f6ff;
      border-radius: 8px;
      padding: 10px;
      margin-top: 12px !important;
      font-size: 10px;
      line-height: 1.6;
      color: #76749a
    }

    .flow-note .icon {
      width: 14px;
      height: 14px;
      margin-top: 1px
    }

    .layer-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 7px
    }

    .layer-grid .btn {
      padding: 8px 5px;
      font-size: 11px
    }


    .template-list {
      padding: 0 13px 15px;
      display: grid;
      gap: 12px
    }

    .template-card {
      width: 100%;
      padding: 6px;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
      text-align: left;
      overflow: hidden
    }

    .template-card img {
      display: block;
      width: 100%;
      aspect-ratio: 1.414;
      object-fit: cover;
      border-radius: 6px;
      background: #f3f4f6
    }

    .template-card:hover {
      border-color: #b3abf8;
      box-shadow: 0 4px 12px #5145b00e
    }

    .template-card[aria-pressed=true] {
      border-color: var(--accent);
      box-shadow: 0 0 0 1px var(--accent)
    }

    .template-caption {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 5px 4px;
      font-size: 11px;
      font-weight: 550
    }

    .template-caption span:last-child {
      color: #a0a9b8;
      font-size: 10px;
      font-weight: 400
    }

    .template-card[aria-pressed=true] .template-caption span:last-child {
      color: var(--accent)
    }

    .templates-intro {
      padding: 0 17px 13px;
      margin: 0;
      font-size: 11px;
      line-height: 1.6;
      color: var(--muted)
    }

    .toast-host {
      position: fixed;
      z-index: 99;
      right: 20px;
      bottom: 20px;
      display: grid;
      gap: 9px;
      max-width: min(380px, calc(100vw - 40px))
    }

    .toast {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      background: #fff;
      border: 1px solid #dde4ee;
      border-left: 3px solid var(--accent);
      border-radius: 10px;
      padding: 13px 12px 13px 15px;
      box-shadow: 0 7px 30px #12234720;
      font-size: 12px;
      line-height: 1.5;
      animation: toast-in .18s ease
    }

    .toast[data-kind=success] {
      border-left-color: #15866a
    }

    .toast[data-kind=error] {
      border-left-color: var(--danger)
    }

    .toast button {
      border: 0;
      background: transparent;
      color: #91a0b1;
      margin-left: auto;
      font-size: 17px;
      line-height: 1
    }

    .toast>span {
      overflow-wrap: anywhere
    }

    @keyframes toast-in {
      from {
        opacity: 0;
        transform: translateY(8px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    .load-mask {
      position: absolute;
      inset: 0;
      z-index: 7;
      display: grid;
      place-content: center;
      gap: 12px;
      background: #f2f5faed;
      text-align: center;
      font-size: 12px;
      color: var(--muted)
    }

    .spinner {
      width: 24px;
      height: 24px;
      border: 2px solid #d9dfef;
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin .7s linear infinite;
      margin: 0 auto
    }

    @keyframes spin {
      to {
        transform: rotate(360deg)
      }
    }

    .fatal-error {
      margin: 18px 24px;
      padding: 16px;
      border: 1px solid #edc9d1;
      background: #fff1f4;
      border-radius: 10px;
      color: var(--danger);
      font-size: 13px
    }

    .footer-brand {
      text-align: center;
      padding: 12px;
      border-top: 1px solid var(--line);
      font-size: 10px;
      color: #a4acb9;
      letter-spacing: .2px
    }

    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0
    }

    @media(min-width:1151px) {

      .left-panel,
      .right-panel {
        max-height: calc(100dvh - 121px);
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #dce2ec transparent
      }
    }

    @media(max-width:1250px) {
      .editor-grid {
        grid-template-columns: 212px minmax(0, 1fr) 222px;
        gap: 12px;
        padding: 18px 15px
      }

      .topbar {
        padding: 14px 18px;
        gap: 19px
      }

      .header-copy {
        padding-left: 19px
      }

      .save-state {
        display: none
      }

      .toolbar .font-field {
        width: 115px
      }

      .toolbar {
        gap: 5px
      }

      .board {
        padding: 28px 16px
      }

      .section {
        padding: 15px
      }

      .details-body {
        padding: 0 15px 15px
      }


    }

    @media(max-width:1000px) {
      .editor-grid {
        grid-template-columns: 210px minmax(0, 1fr)
      }

      .right-panel {
        grid-column: 2;
        max-height: none
      }

      .right-panel .panel-head {
        padding-bottom: 10px
      }

      .right-panel .section {
        padding: 15px
      }

      .board {
        max-height: none;
        min-height: 350px
      }

      .header-copy .breadcrumb {
        display: none
      }

      .header-copy {
        border-left: 0;
        padding-left: 0
      }

      .topbar {
        gap: 20px
      }

      .brand small {
        display: none
      }

      .below-board {
        margin-bottom: 0
      }
    }

    @media(max-width:700px) {
      .topbar {
        padding: 13px 14px;
        flex-wrap: wrap;
        gap: 10px 17px
      }

      .brand strong {
        font-size: 14px
      }

      .brand-mark {
        width: 32px;
        height: 34px
      }

      .header-copy {
        flex: 1
      }

      h1 {
        font-size: 16px
      }

      .header-actions {
        width: 100%;
        justify-content: flex-end
      }

      .header-actions .btn {
        flex: 1
      }

      .save-state {
        display: flex;
        margin-right: auto;
        font-size: 10px
      }

      .editor-grid {
        grid-template-columns: minmax(0, 1fr);
        padding: 14px;
        gap: 14px
      }

      .workspace {
        grid-row: 1
      }

      .right-panel {
        grid-column: auto
      }

      .left-panel {
        grid-row: 2
      }

      .board {
        min-height: 260px;
        padding: 20px 12px
      }

      .toolbar {
        gap: 5px
      }

      .toolbar .font-field {
        width: 112px
      }


      .workspace-foot {
        padding: 8px
      }

      .below-board {
        font-size: 10px
      }

      .template-list {
        grid-template-columns: 1fr 1fr
      }

      .tag-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr))
      }

      .tag-btn {
        justify-content: center
      }

      .left-panel #insertPanel>.section:first-child .stack {
        grid-template-columns: 1fr 1fr
      }

      .left-panel #insertPanel>.section:first-child .stack .btn {
        font-size: 11px
      }

      .workspace-label {
        margin-bottom: 10px
      }

      .workspace-label .overline {
        font-size: 9px
      }
    }

    /* Revisão v3: controles agrupados, campos sem repetição e edição com foco. */
    .toolbar { gap: 10px 12px; padding: 12px; align-items: flex-end; }
    .tool-group { display: flex; align-items: flex-end; gap: 7px; min-width: 0; }
    .tool-section { display: grid; gap: 4px; }
    .tool-caption { display: block; margin-bottom: 4px; font-size: 10px; font-weight: 600; color: var(--muted); line-height: 1.25; }
    .tool-section > .tool-caption { margin-bottom: 0; }
    .button-group { display: flex; gap: 3px; }
    .button-group .btn { border-color: transparent; background: #f5f6fa; }
    .button-group .btn:hover:not(:disabled) { border-color: #d0cbed; background: #efedf9; }
    .button-group .btn.is-active { background: #eae7ff; color: #463ac1; border-color: #cfc8fb; box-shadow: inset 0 0 0 1px #e3ddff; }
    .toolbar .font-field { width: 126px; }
    .toolbar .size-field { width: 62px; }
    .toolbar .color-field { width: 35px; }
    .toolbar .color-field input { display: block; }
    .toolbar .alignment-buttons .btn { width: 33px; }
    .property-title h3 { margin: 0; }
    #textAlignmentInfo { padding: 3px 6px; font-size: 9px; background: #f1f2f8; color: #5a5874; }
    .edit-text-button { margin-top: 11px; }
    .inline-details { margin-top: 12px; border-top: 1px solid var(--line); padding-top: 10px; }
    .inline-details > summary { cursor: pointer; font-size: 11px; color: #64728a; padding: 3px 0; }
    .inline-details[open] > summary { margin-bottom: 10px; }
    .tag-options .check-row { margin-top: 5px; }
    #tagInstruction { min-height: 36px; margin-top: 10px; font-size: 10px; }
    .workspace-label { flex-wrap: wrap; min-height: 32px; margin-bottom: 11px; }
    .workspace-label > div { flex-wrap: wrap; }
    .workspace-focus { min-height: 31px; font-size: 11px; padding: 5px 9px; color: #58667d; background: transparent; }
    .workspace-focus[aria-pressed=true] { color: var(--accent); border-color: #d6d2ff; background: var(--accent-soft); }
    .workspace-focus .icon { width: 15px; height: 15px; }
    .focus-mode .left-panel, .focus-mode .right-panel { display: none; }
    .focus-mode .editor-grid { grid-template-columns: minmax(0, 1fr); max-width: 1540px; }
    .focus-mode .workspace { grid-column: 1; }
    .focus-mode .board { min-height: 480px; }
    @media (min-width: 1001px) { .left-panel, .right-panel { position: sticky; top: 18px; } }
    @media (max-width: 700px) {
      .toolbar { gap: 10px 12px; padding: 10px; }
      .toolbar .font-field { width: 120px; }
      .tool-caption { font-size: 10px; }
      .tag-btn { font-size: 11px; }
      .header-actions { gap: 7px; }
      .save-state { width: 100%; margin-right: 0; margin-bottom: 2px; }
      .workspace-focus { font-size: 10px; }
      .focus-mode .board { min-height: 280px; }
    }
    @media (max-width: 380px) {
      .tag-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .workspace-label .overline { display: none; }
      .template-list { grid-template-columns: 1fr; }
      .save-state { width: 100%; margin-bottom: 2px; }
      .header-actions .btn { font-size: 11px; padding: 8px 9px; gap: 6px; }
    }

    /* Salvamento: informação discreta e visível em todas as larguras. */
    .save-state { display: flex; padding: 6px 9px; border-radius: 7px; background: #f6f8fb;
      min-width: 0; max-width: 270px; line-height: 1.35; }
    .save-state .dot { flex: none; }
    .save-state[data-state=saving] .dot { width: 10px; height: 10px; background: transparent;
      border: 2px solid #d8d4fb; border-top-color: var(--accent); animation: spin .8s linear infinite; }
    .save-state[data-state=waiting] .dot, .save-state[data-state=retry] .dot { background: #d89b2b; }
    .save-state[data-state=error] { background: #fff1f3; color: var(--danger); }
    .save-state[data-state=saved] { background: #edf8f3; color: #13795c; }
    @media(max-width:1100px) {
      .topbar { flex-wrap: wrap; }
      .header-actions { gap: 8px; }
      .save-state { display: flex; font-size: 11px; margin-right: 0; }
    }
    @media(max-width:700px) {
      .save-state { display: flex; flex-basis: 100%; max-width: none; justify-content: center; font-size: 11px; }
    }

    @media(prefers-reduced-motion:reduce) {

      *,
      *:before,
      *:after {
        animation: none !important;
        transition: none !important
      }
    }
  </style>
</head>

<body>
  <svg width="0" height="0" aria-hidden="true" style="position:absolute;overflow:hidden">
    <defs>
      <symbol id="i-award" viewBox="0 0 24 24">
        <path d="M4 4h16v12H4zM7 8h10M7 11h5" />
        <circle cx="16" cy="16" r="3" />
        <path d="m14 19-1 4 3-2 3 2-1-4" />
      </symbol>
      <symbol id="i-plus" viewBox="0 0 24 24">
        <path d="M12 5v14M5 12h14" />
      </symbol>
      <symbol id="i-minus" viewBox="0 0 24 24">
        <path d="M5 12h14" />
      </symbol>
      <symbol id="i-text" viewBox="0 0 24 24">
        <path d="M4 6V4h16v2M12 4v16M8 20h8" />
      </symbol>
      <symbol id="i-paragraph" viewBox="0 0 24 24">
        <path d="M4 5h16M4 10h16M4 15h16M4 20h10" />
      </symbol>
      <symbol id="i-image" viewBox="0 0 24 24">
        <rect x="3" y="3" width="18" height="18" rx="3" />
        <circle cx="8" cy="8" r="1.5" />
        <path d="m3 17 6-6 4 4 3-3 5 5" />
      </symbol>
      <symbol id="i-save" viewBox="0 0 24 24">
        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12l4 4v12a2 2 0 0 1-2 2Z" />
        <path d="M7 3v6h10V3M7 21v-8h10v8" />
      </symbol>
      <symbol id="i-download" viewBox="0 0 24 24">
        <path d="M12 3v12m-5-5 5 5 5-5M4 16v4h16v-4" />
      </symbol>
      <symbol id="i-square" viewBox="0 0 24 24">
        <rect x="4" y="4" width="16" height="16" rx="1" />
      </symbol>
      <symbol id="i-circle" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="8" />
      </symbol>
      <symbol id="i-triangle" viewBox="0 0 24 24">
        <path d="m12 3 10 18H2Z" />
      </symbol>
      <symbol id="i-line" viewBox="0 0 24 24">
        <path d="M3 12h18" />
      </symbol>
      <symbol id="i-dash" viewBox="0 0 24 24">
        <path d="M3 12h4m3 0h4m3 0h4" />
      </symbol>
      <symbol id="i-arrow" viewBox="0 0 24 24">
        <path d="M3 12h18m-5-5 5 5-5 5" />
      </symbol>
      <symbol id="i-copy" viewBox="0 0 24 24">
        <rect x="8" y="8" width="12" height="13" rx="2" />
        <path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3" />
      </symbol>
      <symbol id="i-trash" viewBox="0 0 24 24">
        <path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7" />
      </symbol>
      <symbol id="i-front" viewBox="0 0 24 24">
        <rect x="8" y="3" width="13" height="13" rx="2" />
        <path d="M8 8H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-3" />
      </symbol>
      <symbol id="i-back" viewBox="0 0 24 24">
        <rect x="3" y="8" width="13" height="13" rx="2" />
        <path d="M8 8V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-3" />
      </symbol>
      <symbol id="i-cursor" viewBox="0 0 24 24">
        <path d="m4 3 16 10-8 1-4 7Z" />
      </symbol>
      <symbol id="i-check" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6" />
      </symbol>
      <symbol id="i-info" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9" />
        <path d="M12 11v6M12 7h.01" />
      </symbol>
      <symbol id="i-align-left" viewBox="0 0 24 24"><path d="M4 5h16M4 10h10M4 15h16M4 20h10" /></symbol>
      <symbol id="i-align-center" viewBox="0 0 24 24"><path d="M4 5h16M7 10h10M4 15h16M7 20h10" /></symbol>
      <symbol id="i-align-right" viewBox="0 0 24 24"><path d="M4 5h16M10 10h10M4 15h16M10 20h10" /></symbol>
      <symbol id="i-align-justify" viewBox="0 0 24 24"><path d="M4 5h16M4 10h16M4 15h16M4 20h16" /></symbol>
      <symbol id="i-expand" viewBox="0 0 24 24"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5" /></symbol>
      <symbol id="i-edit" viewBox="0 0 24 24"><path d="m15 5 4 4M4 20l4-1L21 6a2.8 2.8 0 0 0-4-4L4 15Z" /></symbol>
    </defs>
  </svg>
  <header class="topbar">
    <div class="brand">
      <div class="brand-mark"><svg class="icon">
          <use href="#i-award" />
        </svg></div>
      <div><strong>SICAD</strong><small>Certificados</small></div>
    </div>
    <div class="header-copy">
      <p class="breadcrumb">Certificados <span>/</span> Modelos</p>
      <h1>Editor de certificados</h1>
    </div>
    <div class="header-actions">
      <div class="save-state" id="saveState" data-state="idle" role="status" aria-live="polite" aria-atomic="true" title="As alterações são salvas automaticamente após uma breve pausa."><span class="dot" aria-hidden="true"></span><span id="saveStateText">Preparando salvamento…</span></div>
      <button class="btn" type="button" data-action="download" title="Exportar a imagem do modelo. As tags permanecem como tags."><svg class="icon">
          <use href="#i-download" />
        </svg>Exportar imagem</button>
      <button class="btn primary" type="button" data-action="save" id="saveButton"><svg class="icon">
          <use href="#i-save" />
        </svg><span id="saveButtonText">Salvar agora</span></button>
    </div>
  </header>
  <div class="fatal-error" id="fatalError" role="alert" hidden></div>
  <main class="editor-grid" id="editorGrid">
    <aside class="panel left-panel" aria-label="Elementos e modelos">
      <div class="panel-head">
        <h2>Crie seu certificado</h2>
      </div>
      <div class="tabs" role="tablist" aria-label="Ferramentas do editor">
        <button class="tab" id="tabInsert" role="tab" aria-controls="insertPanel" aria-selected="true" data-tab="insert" type="button">Inserir</button>
        <button class="tab" id="tabTemplates" role="tab" aria-controls="modelsPanel" aria-selected="false" tabindex="-1" data-tab="models" type="button">Modelos</button>
      </div>
      <div id="insertPanel" role="tabpanel" aria-labelledby="tabInsert">
        <section class="section">
          <h3>Texto e imagem</h3>
          <div class="stack">
            <button class="btn full" type="button" data-action="addText"><svg class="icon">
                <use href="#i-text" />
              </svg>Adicionar texto</button>
            <button class="btn soft full" type="button" data-action="paragraph"><svg class="icon">
                <use href="#i-paragraph" />
              </svg>Parágrafo com tags</button>
            <button class="btn full" type="button" data-action="upload"><svg class="icon">
                <use href="#i-image" />
              </svg>Adicionar imagem</button>
          </div><input type="file" id="imgLoader" accept="image/png,image/jpeg,image/webp" hidden>
        </section>
        <section class="section" id="tagsPanel" aria-labelledby="tagsHeading">
          <h3 id="tagsHeading">Campos automáticos</h3>
          <div class="tag-grid">
            <button class="tag-btn" data-insert-tag="{{NOME}}" type="button" title="Inserir nome do participante: {{NOME}}">Nome</button>
            <button class="tag-btn" data-insert-tag="{{ATIVIDADE}}" type="button" title="Inserir nome da atividade: {{ATIVIDADE}}">Atividade</button>
            <button class="tag-btn" data-insert-tag="{{CODIGO}}" type="button" title="Inserir código do certificado: {{CODIGO}}">Código</button>
            <button class="tag-btn" data-insert-tag="{{CARGA_HORARIA}}" type="button" title="Inserir carga horária: {{CARGA_HORARIA}}">Carga horária</button>
            <button class="tag-btn" data-insert-tag="{{EVENTO}}" type="button" title="Inserir nome do evento: {{EVENTO}}">Evento</button>
            <button class="tag-btn" data-insert-tag="{{DATA_EMISSAO}}" type="button" title="Inserir data de emissão: {{DATA_EMISSAO}}">Data de emissão</button>
          </div>
          <p class="hint" id="tagInstruction">Selecione um texto para inserir o campo nele. Sem seleção, será criada uma caixa.</p>
          <details class="inline-details tag-options">
            <summary>Opções de inserção</summary>
            <label class="check-row"><input type="checkbox" id="tagSeparate"><span>Inserir em uma caixa separada</span></label>
          </details>
        </section>
        <details class="details-block">
          <summary>Formas e linhas</summary>
          <div class="details-body">
            <div class="shape-grid"><button type="button" class="btn" data-shape="rect"><svg class="icon">
                  <use href="#i-square" />
                </svg>Quadrado</button><button type="button" class="btn" data-shape="circle"><svg class="icon">
                  <use href="#i-circle" />
                </svg>Círculo</button><button type="button" class="btn" data-shape="triangle"><svg class="icon">
                  <use href="#i-triangle" />
                </svg>Triângulo</button></div>
            <div class="line-grid"><button type="button" class="btn" data-line="normal"><svg class="icon">
                  <use href="#i-line" />
                </svg>Linha</button><button type="button" class="btn" data-line="dashed"><svg class="icon">
                  <use href="#i-dash" />
                </svg>Tracejada</button><button type="button" class="btn" data-line="arrow"><svg class="icon">
                  <use href="#i-arrow" />
                </svg>Seta</button><button type="button" class="btn" data-line="dashedArrow">Seta tracejada</button></div>
            <div class="inline-row" style="margin-top:14px"><input type="color" id="shapeColor" value="#4f46e5" aria-label="Cor da forma"><span class="hint">Cor da forma</span></div><label class="check-row"><input type="checkbox" id="noFill"><span>Sem preenchimento</span></label>
          </div>
        </details>
      </div>
      <div id="modelsPanel" role="tabpanel" aria-labelledby="tabTemplates" hidden>
        <p class="templates-intro">Escolha a base do certificado. Seus textos e elementos serão mantidos.</p>
        <div id="templates" class="template-list">
          <button class="template-card" type="button" data-template="https://sicad.linceonline.com.br/controller/assets/certificados/Certificado_1.jpg" aria-pressed="false"><img src="https://sicad.linceonline.com.br/controller/assets/certificados/Certificado_1.jpg" alt="Moldura azul-marinho e selo dourado">
            <div class="template-caption"><span>Institucional</span><span>01</span></div>
          </button>
          <button class="template-card" type="button" data-template="https://sicad.linceonline.com.br/controller/assets/certificados/Certificado_2.jpg" aria-pressed="false"><img src="https://sicad.linceonline.com.br/controller/assets/certificados/Certificado_2.jpg" alt="Moldura ornamental preta e medalha dourada">
            <div class="template-caption"><span>Clássico</span><span>02</span></div>
          </button>
          <button class="template-card" type="button" data-template="https://sicad.linceonline.com.br/controller/assets/certificados/Certificado_3.jpg" aria-pressed="false"><img src="https://sicad.linceonline.com.br/controller/assets/certificados/Certificado_3.jpg" alt="Fundo azul e cinza com área branca">
            <div class="template-caption"><span>Contemporâneo</span><span>03</span></div>
          </button>
          <button class="template-card" type="button" data-template="https://sicad.linceonline.com.br/controller/assets/certificados/Certificado_4.jpg" aria-pressed="false"><img src="https://sicad.linceonline.com.br/controller/assets/certificados/Certificado_4.jpg" alt="Moldura verde com medalha no alto">
            <div class="template-caption"><span>Essencial</span><span>04</span></div>
          </button>
        </div>
      </div>
      <div class="footer-brand">Seu conteúdo. Sua identidade.</div>
    </aside>
    <section class="workspace" aria-label="Área de edição">
      <div class="workspace-label">
        <div><span class="overline">Área de criação</span><span class="badge" id="activityBadge">Novo modelo</span></div>
        <button class="btn workspace-focus" type="button" data-action="focusMode" id="focusModeButton" aria-pressed="false" title="Ocultar os painéis laterais sem alterar o certificado"><svg class="icon" aria-hidden="true"><use href="#i-expand" /></svg><span id="focusModeLabel">Ampliar área</span></button>
      </div>
      <p id="layoutStatus" class="warning" role="status" hidden></p>
      <div id="toolbar" class="toolbar" aria-label="Formatação do texto selecionado">
        <div class="tool-group">
          <label class="font-field"><span class="tool-caption">Fonte</span><select id="fontFamily" data-text-control disabled>
              <option value="Arial">Arial</option><option value="Times New Roman">Times New Roman</option>
              <option value="Courier New">Courier New</option><option value="Verdana">Verdana</option><option value="Georgia">Georgia</option>
            </select></label>
          <label class="size-field"><span class="tool-caption">Tamanho</span><input id="fontSize" data-text-control type="number" value="24" min="1" max="400" step="1" title="Tamanho da fonte em pixels do modelo" disabled></label>
          <label class="color-field"><span class="tool-caption">Cor</span><input type="color" id="fontColor" data-text-control value="#000000" disabled></label>
        </div>
        <div class="tool-group tool-section">
          <span class="tool-caption" id="styleGroupLabel">Estilo</span>
          <div class="button-group" role="group" aria-labelledby="styleGroupLabel">
            <button class="btn icon-btn style-btn" id="boldButton" data-style="bold" data-text-control type="button" title="Negrito" aria-label="Negrito" aria-pressed="false" disabled>B</button>
            <button class="btn icon-btn style-btn italic" id="italicButton" data-style="italic" data-text-control type="button" title="Itálico" aria-label="Itálico" aria-pressed="false" disabled>I</button>
            <button class="btn icon-btn style-btn underline" id="underlineButton" data-style="underline" data-text-control type="button" title="Sublinhado" aria-label="Sublinhado" aria-pressed="false" disabled>U</button>
          </div>
        </div>
        <div class="tool-group tool-section alignment-section">
          <span class="tool-caption" id="alignmentGroupLabel">Alinhamento</span>
          <div class="button-group alignment-buttons" role="group" aria-labelledby="alignmentGroupLabel">
            <button class="btn icon-btn" data-align="left" data-text-control type="button" title="Alinhar à esquerda" aria-label="Alinhar à esquerda" aria-pressed="false" disabled><svg class="icon" aria-hidden="true"><use href="#i-align-left" /></svg></button>
            <button class="btn icon-btn" data-align="center" data-text-control type="button" title="Centralizar texto" aria-label="Centralizar texto" aria-pressed="false" disabled><svg class="icon" aria-hidden="true"><use href="#i-align-center" /></svg></button>
            <button class="btn icon-btn" data-align="right" data-text-control type="button" title="Alinhar à direita" aria-label="Alinhar à direita" aria-pressed="false" disabled><svg class="icon" aria-hidden="true"><use href="#i-align-right" /></svg></button>
            <button class="btn icon-btn" data-align="justify" data-text-control type="button" title="Justificado: última linha à esquerda" aria-label="Justificar texto" aria-pressed="false" disabled><svg class="icon" aria-hidden="true"><use href="#i-align-justify" /></svg></button>
          </div>
        </div>
      </div>
      <div class="board" id="board">
        <div class="paper-holder">
          <div id="canvasStage">
            <canvas id="c" width="800" height="566" aria-label="Certificado editável"></canvas>
            <svg id="safeOverlay" aria-hidden="true">
              <rect id="safeRect" fill="none" />
            </svg>
            <div class="empty-paper" id="emptyPaper"><svg class="icon">
                <use href="#i-award" />
              </svg><strong>Um certificado com a sua identidade.</strong>
              <p>Escolha um modelo, adicione seu texto e personalize os detalhes.</p><button type="button" class="btn soft" data-action="showModels">Escolher modelo</button>
            </div>
          </div>
        </div>
        <div id="loadMask" class="load-mask" hidden>
          <div class="spinner"></div><span id="loadMaskText">Carregando modelo…</span>
        </div>
      </div>
      <div class="workspace-foot"><span id="objectStatus">Nenhum elemento</span>
        <div class="zoom-controls"><button class="btn icon-btn ghost" type="button" data-action="zoomOut" aria-label="Diminuir visualização"><svg class="icon">
              <use href="#i-minus" />
            </svg></button><label><span class="sr-only">Zoom da visualização</span><select id="zoomSelect">
              <option value="fit">Ajustar</option>
              <option value="0.5">50%</option>
              <option value="0.75">75%</option>
              <option value="1">100%</option>
              <option value="1.25">125%</option>
              <option value="1.5">150%</option>
            </select></label><button class="btn icon-btn ghost" type="button" data-action="zoomIn" aria-label="Ampliar visualização"><svg class="icon">
              <use href="#i-plus" />
            </svg></button></div>
      </div>
      <p class="below-board">Salvamento automático ativo · Duplo clique para editar · <kbd>Ctrl</kbd> + <kbd>S</kbd> para salvar agora</p>
    </section>
    <aside class="panel right-panel" aria-label="Propriedades do certificado">
      <div class="panel-head">
        <h2>Propriedades</h2><svg class="icon" style="color:#a6afbd">
          <use href="#i-cursor" />
        </svg>
      </div>
      <div id="propertyEmpty" class="property-empty"><svg class="icon">
          <use href="#i-cursor" />
        </svg><span>Selecione um elemento</span>
        <p>Os ajustes do texto e das camadas aparecem aqui.</p>
      </div>
      <div id="objectProperties" hidden>
        <div class="section" style="padding-top:13px">
          <div class="property-title"><strong id="selectionLabel">Texto selecionado</strong></div>
          <div id="selectedExcerpt" class="property-tag"></div>
          <button class="btn soft full edit-text-button" type="button" data-action="editText" id="editTextButton" data-text-control disabled hidden><svg class="icon" aria-hidden="true"><use href="#i-edit" /></svg>Editar texto</button>
        </div>
        <section class="section" id="textProperties" hidden>
          <div class="property-title"><h3>Caixa de texto</h3><span class="badge" id="textAlignmentInfo">Esquerda</span></div>
          <div class="two-cols"><label class="field"><span>Largura</span>
              <div class="unit-input"><input type="number" id="textBoxWidth" min="1" step="1" value="500" data-text-control disabled><span>px</span></div>
            </label><label class="field"><span>Entrelinhas</span><input type="number" id="lineHeight" min="0.8" max="3" step="0.05" value="1.25" data-text-control disabled></label></div>
          <label class="check-row"><input type="checkbox" id="wrapToMargin" data-text-control disabled><span>Ocupar até a margem direita</span></label>
          <details class="inline-details height-options">
            <summary id="heightLimitSummary">Limite de altura (opcional)</summary>
            <label class="field"><span>Altura máxima reservada</span><div class="unit-input"><input type="number" id="textMaxHeight" min="0" step="1" value="0" data-text-control disabled><span>px</span></div></label>
            <p class="hint">0 permite crescer até a margem inferior.</p>
          </details>
          <p class="flow-note"><svg class="icon" aria-hidden="true"><use href="#i-info" /></svg><span>Quebra automática, sem diminuir a fonte. Deixe espaço para os demais elementos.</span></p>
        </section>
        <section class="section">
          <h3>Organizar elemento</h3>
          <div class="layer-grid"><button class="btn" data-action="front" type="button"><svg class="icon">
                <use href="#i-front" />
              </svg>À frente</button><button class="btn" data-action="back" type="button"><svg class="icon">
                <use href="#i-back" />
              </svg>Ao fundo</button><button class="btn" data-action="duplicate" type="button"><svg class="icon">
                <use href="#i-copy" />
              </svg>Duplicar</button><button class="btn danger" data-action="delete" type="button"><svg class="icon">
                <use href="#i-trash" />
              </svg>Excluir</button></div>
        </section>
      </div>
      <details class="details-block" id="marginDetails">
        <summary>Margens do certificado</summary>
        <div class="details-body">
          <div class="two-cols"><label class="field"><span>Esquerda (%)</span><input id="marginLeft" type="number" min="0" max="45" value="10" step="0.5"></label><label class="field"><span>Direita (%)</span><input id="marginRight" type="number" min="0" max="45" value="10" step="0.5"></label><label class="field"><span>Superior (%)</span><input id="marginTop" type="number" min="0" max="45" value="10" step="0.5"></label><label class="field"><span>Inferior (%)</span><input id="marginBottom" type="number" min="0" max="45" value="10" step="0.5"></label></div>
          <label class="check-row"><input id="showGuides" type="checkbox" checked><span>Exibir guias da área útil</span></label>
          <p class="hint">As guias são apenas de edição. Não aparecem no arquivo exportado.</p>
        </div>
      </details>
      <div class="section"><span class="overline">Formato do modelo</span>
        <div class="inline-row" style="justify-content:space-between;margin-top:8px"><span class="hint" id="canvasDimensions">800 × 566 px</span><span class="badge">Paisagem</span></div>
      </div>
    </aside>
  </main>
  <div class="toast-host" id="toastHost" aria-live="polite" aria-atomic="false"></div>
  <noscript>
    <p class="fatal-error">Ative o JavaScript para utilizar o editor de certificados.</p>
  </noscript>
  <script>
    (() => {
      'use strict';
      const VERSION = '2026-09-12-autosave-v4';
      // URLs mantidas do arquivo recebido. Altere somente aqui em homologação.
      const API_BASE = 'https://sicad.linceonline.com.br/controller/';
      const $ = id => document.getElementById(id);

      function toast(message, kind = 'info', duration = 5500) {
        const item = document.createElement('div');
        item.className = 'toast';
        item.dataset.kind = kind;
        const text = document.createElement('span');
        text.textContent = String(message);
        const close = document.createElement('button');
        close.type = 'button';
        close.textContent = '×';
        close.setAttribute('aria-label', 'Fechar aviso');
        close.addEventListener('click', () => item.remove());
        item.append(text, close);
        $('toastHost').append(item);
        while ($('toastHost').children.length > 3) $('toastHost').firstElementChild.remove();
        window.setTimeout(() => item.remove(), duration);
      }
      if (!window.fabric) {
        $('fatalError').hidden = false;
        $('fatalError').textContent = 'Não foi possível carregar o editor gráfico. Verifique a conexão e o acesso ao CDN do Fabric.js, depois recarregue a página.';
        $('editorGrid').inert = true;
        document.querySelectorAll('.header-actions button').forEach(b => b.disabled = true);
        return;
      }
      const canvas = new fabric.Canvas('c', {
        preserveObjectStacking: true
      });
      canvas.backgroundColor = '#ffffff';
      const EXTRA_PROPERTIES = ['name', 'dataTipo', 'sicadTag', 'sicadAutoFit', 'autoFit', 'autoFitMode',
        'sicadNoWrap', 'minFontSize', 'sicadBoxWidth', 'sicadMaxHeight', 'sicadWrapToMargin',
        'sicadBackground', 'modeloSrc', 'excludeFromExport'
      ];
      const atividade_id = new URLSearchParams(location.search).get('atividade_id');
      let safeArea = defaultSafeArea(canvas.width, canvas.height);
      let applyingWrap = false,
        selectedTemplateSrc = null,
        savedSelection = null;
      let isLoading = false,
        isSaving = false,
        dirty = false,
        revision = 0;
      let modelRequest = 0,
        viewScale = 1,
        zoomMode = 'fit';
      let resizeFrame = 0;
      // Uma única requisição por vez; digitação contínua gera checkpoints a cada 5 s.
      const AUTOSAVE_DELAY_MS = 800;
      const AUTOSAVE_MAX_WAIT_MS = 5000;
      const AUTOSAVE_RETRY_MS = [2000, 5000, 10000, 30000];
      let autosaveTimer = 0, pendingSince = 0, lastChangeAt = 0;
      let initialLoadVerified = false, pointerOnCanvas = false;
      let retryAttempt = 0, retryAt = 0, autosavePaused = false;
      let lastSaveFailure = null, lastSavedJSON = null;
      let unloadGuardActive = false;

      // A lista visível é a única origem dos campos permitidos para NOVAS inserções.
      // Conteúdo de modelos antigos não é removido automaticamente.
      const allowedTags = new Set(Array.from(document.querySelectorAll('[data-insert-tag]'), button => button.dataset.insertTag));
      const ALIGN_LABELS = Object.freeze({ left: 'Esquerda', center: 'Centro', right: 'Direita', justify: 'Justificado' });
      let focusMode = false;

      function alignmentChoice(value) {
        return String(value || '').startsWith('justify') ? 'justify' :
          (Object.prototype.hasOwnProperty.call(ALIGN_LABELS, value) ? value : 'left');
      }

      // Fabric: justify-left preserva a última linha de cada parágrafo.
      // TCPDF do projeto: o contrato salvo continua usando o valor "justify".
      function editorAlignment(value) {
        return value === 'justify' ? 'justify-left' : (value || 'left');
      }

      function serializeTextAlignment(state) {
        (state.objects || []).forEach(function visit(obj) {
          if (isTextObject(obj) && obj.textAlign === 'justify-left') obj.textAlign = 'justify';
          if (Array.isArray(obj.objects)) obj.objects.forEach(visit);
        });
        return state;
      }

      function syncTagInstruction() {
        const selectedText = isTextObject(canvas.getActiveObject());
        $('tagInstruction').textContent = $('tagSeparate').checked ?
          'Cada campo será inserido em uma nova caixa de texto.' : selectedText ?
          'O campo entra no cursor. Depois, continue digitando normalmente.' :
          'Selecione um texto para inserir o campo nele. Sem seleção, será criada uma caixa.';
      }

      // A fonte e as dimensões do arquivo não dependem do zoom visual.
      function defaultSafeArea(w, h) {
        return {
          left: w * 0.10,
          top: h * 0.10,
          right: w * 0.90,
          bottom: h * 0.90
        };
      }

      function validSafeArea(area, w, h) {
        return area && ['left', 'top', 'right', 'bottom'].every(k => Number.isFinite(Number(area[k]))) &&
          Number(area.left) >= 0 && Number(area.top) >= 0 &&
          Number(area.right) <= w && Number(area.bottom) <= h &&
          Number(area.right) > Number(area.left) && Number(area.bottom) > Number(area.top);
      }

      function configureFixedWrap(obj, area = safeArea) {
        if (!isTextObject(obj) || obj.type !== 'textbox') return;
        const point = obj.getPointByOrigin('left', 'top');
        const scaleX = Math.abs(Number(obj.scaleX) || 1);
        let desiredWidth = Number(obj.sicadBoxWidth) || Number(obj.width) || 300;
        if (Math.abs(Number(obj.angle) || 0) < 0.001 && !obj.group) {
          point.x = Math.max(area.left, point.x);
          const available = (area.right - point.x) / scaleX;
          if (available > 1) {
            desiredWidth = obj.sicadWrapToMargin ? available : Math.min(desiredWidth, available);
          }
        }
        desiredWidth = Math.max(1, desiredWidth);
        obj.set({
          autoFit: false,
          sicadAutoFit: false,
          autoFitMode: 'wrap',
          sicadNoWrap: false,
          lockScalingY: true,
          lockScalingFlip: true,
          minWidth: Math.min(20, desiredWidth),
          splitByGrapheme: false,
          width: desiredWidth
        });
        delete obj.sicadBoxHeight;
        delete obj.boxHeight;
        obj.initDimensions();
        // Fallback apenas quando uma palavra/código é maior que a caixa inteira.
        if (obj.width > desiredWidth + 0.1) {
          obj.set({
            splitByGrapheme: true,
            width: desiredWidth
          });
          obj.initDimensions();
        }
        obj.sicadBoxWidth = desiredWidth;
        obj.setPositionByOrigin(point, 'left', 'top');
        obj.setControlsVisibility({
          tl: false,
          tr: false,
          bl: false,
          br: false,
          mt: false,
          mb: false,
          ml: true,
          mr: true,
          mtr: true
        });
        obj.setCoords();
      }

      function layoutWarnings(objects, area) {
        const warnings = [];
        objects.filter(isTextObject).forEach((obj, index) => {
          const b = obj.getBoundingRect(true, true);
          if (b.left < area.left - 1 || b.top < area.top - 1 ||
            b.left + b.width > area.right + 1 || b.top + b.height > area.bottom + 1) {
            warnings.push('Bloco de texto ' + (index + 1) + ': fora da área útil. Reposicione ou amplie a largura.');
          }
          if (Number(obj.sicadMaxHeight) > 0 && obj.height > Number(obj.sicadMaxHeight) + 1) {
            warnings.push('Bloco de texto ' + (index + 1) + ': excedeu a altura máxima reservada.');
          }
        });
        return warnings;
      }

      function normalizeTemplateJSON(json) {
        (json.objects || []).forEach(function visit(obj) {
          if (['text', 'i-text', 'textbox'].includes(obj.type)) {
            obj.type = 'textbox';
            obj.textAlign = editorAlignment(obj.textAlign);
            obj.autoFit = false;
            obj.sicadAutoFit = false;
            obj.sicadNoWrap = false;
            obj.autoFitMode = 'wrap';
            obj.sicadBoxWidth = Number(obj.sicadBoxWidth) || Number(obj.width) || 300;
            delete obj.sicadBoxHeight;
            delete obj.boxHeight;
          }
          if (Array.isArray(obj.objects)) obj.objects.forEach(visit);
        });
        return json;
      }

      function isTextObject(obj) {
        return obj && (obj.type === 'i-text' || obj.type === 'textbox' || obj.type === 'text');
      }

      function isBackground(obj) {
        return !!obj && (obj.sicadBackground || obj.dataTipo === 'modelo_fundo');
      }

      function editableObjects() {
        return canvas.getObjects().filter(obj => !isBackground(obj));
      }

      function setSaveState(state, text, detail = '') {
        $('saveState').dataset.state = state;
        $('saveStateText').textContent = text;
        $('saveState').title = detail || text;
      }

      function beforeUnloadHandler(e) {
        if (!dirty && !isSaving) return;
        e.preventDefault();
        e.returnValue = true;
      }

      function syncUnloadGuard() {
        const needed = dirty || isSaving;
        if (needed === unloadGuardActive) return;
        window[needed ? 'addEventListener' : 'removeEventListener']('beforeunload', beforeUnloadHandler);
        unloadGuardActive = needed;
      }

      function hasActivity() {
        return !!atividade_id && /^[1-9][0-9]*$/.test(atividade_id);
      }

      function hasTemplate() {
        return !!canvas.backgroundImage || !!selectedTemplateSrc || canvas.getObjects().some(isBackground);
      }

      function editingGestureInProgress() {
        const active = canvas.getActiveObject();
        return pointerOnCanvas || !!(active && active.inCompositionMode);
      }

      function clearAutosaveTimer() {
        window.clearTimeout(autosaveTimer);
        autosaveTimer = 0;
      }

      function pendingSaveState() {
        if (isSaving) return;
        if (!hasActivity()) {
          setSaveState('waiting', 'Salvamento indisponível', 'Abra a atividade pelo sistema: falta um atividade_id válido na URL.');
        } else if (!initialLoadVerified) {
          setSaveState('error', 'Recarregue para salvar', 'O modelo existente não pôde ser verificado. Recarregue a página antes de editar para evitar sobrescrevê-lo.');
        } else if (autosavePaused) {
          setSaveState('error', 'Não salvo · tente novamente', lastSaveFailure ? lastSaveFailure.message : 'Use Salvar agora para tentar novamente.');
        } else if (!hasTemplate()) {
          setSaveState('waiting', 'Escolha um modelo', 'O salvamento começa após escolher uma imagem de modelo. Os textos atuais serão mantidos.');
        } else if (retryAt > Date.now()) {
          setSaveState('retry', 'Aguardando nova tentativa', 'As alterações ainda não foram confirmadas. Mantenha a página aberta; o sistema tentará novamente.');
        } else {
          setSaveState('dirty', 'Alterações pendentes…', 'O modelo será salvo automaticamente após uma breve pausa.');
        }
      }

      function scheduleAutosave(immediate = false) {
        clearAutosaveTimer();
        if (!dirty || isLoading || isSaving) return;
        pendingSaveState();
        if (!initialLoadVerified || !hasActivity() || !hasTemplate() || autosavePaused) return;
        const now = Date.now();
        const deadline = immediate ? now : Math.min(lastChangeAt + AUTOSAVE_DELAY_MS,
          (pendingSince || now) + AUTOSAVE_MAX_WAIT_MS);
        let delay = Math.max(0, deadline - now, retryAt - now);
        // Não captura um arraste, uma imagem de fundo em carregamento ou um caractere IME incompleto.
        if (!$('loadMask').hidden || editingGestureInProgress()) delay = Math.max(250, delay);
        autosaveTimer = window.setTimeout(() => {
          autosaveTimer = 0;
          void saveCanvas({ automatic: true });
        }, delay);
      }

      function markDirty() {
        if (isLoading) return;
        dirty = true;
        revision += 1;
        lastChangeAt = Date.now();
        if (!pendingSince) pendingSince = lastChangeAt;
        syncUnloadGuard();
        refreshInfo();
        scheduleAutosave();
      }

      function refreshInfo() {
        const count = editableObjects().length;
        $('objectStatus').textContent = count ? count + (count === 1 ? ' elemento' : ' elementos') : 'Nenhum elemento';
        $('emptyPaper').hidden = !!canvas.backgroundImage || !!selectedTemplateSrc || count > 0;
        $('canvasDimensions').textContent = canvas.width + ' × ' + canvas.height + ' px';
      }

      function showLayoutWarnings() {
        const warnings = layoutWarnings(editableObjects(), safeArea);
        $('layoutStatus').textContent = warnings.join('\n');
        $('layoutStatus').hidden = !warnings.length;
        refreshInfo();
      }

      function updateAllWrapping() {
        if (applyingWrap) return;
        applyingWrap = true;
        try {
          editableObjects().forEach(obj => configureFixedWrap(obj));
        } finally {
          applyingWrap = false;
        }
        canvas.requestRenderAll();
        showLayoutWarnings();
      }

      function styleControls(obj) {
        obj.set({
          borderColor: '#6255d9',
          cornerColor: '#ffffff',
          cornerStrokeColor: '#6255d9',
          transparentCorners: false,
          cornerStyle: 'circle',
          cornerSize: 9,
          editingBorderColor: '#6255d9',
          cursorColor: '#5145cd'
        });
        if (isBackground(obj)) obj.set({
          selectable: false,
          evented: false
        });
      }

      function addObject(obj, select = true) {
        if (isLoading) return;
        const previous = canvas.getActiveObject();
        if (previous && previous.isEditing) previous.exitEditing();
        savedSelection = null;
        canvas.add(obj);
        if (select) canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        syncTextControls();
      }

      function syncTextControls() {
        const obj = canvas.getActiveObject();
        const isText = isTextObject(obj);
        document.querySelectorAll('[data-text-control]').forEach(el => {
          el.disabled = !isText || isLoading;
        });
        $('propertyEmpty').hidden = !!obj;
        $('objectProperties').hidden = !obj;
        $('textProperties').hidden = !isText;
        $('editTextButton').hidden = !isText;
        syncTagInstruction();
        document.querySelectorAll('[data-align]').forEach(button => {
          const on = !!isText && alignmentChoice(obj.textAlign) === button.dataset.align;
          button.classList.toggle('is-active', on);
          button.setAttribute('aria-pressed', String(on));
        });
        if (!isText) document.querySelectorAll('[data-style]').forEach(button => {
          button.classList.remove('is-active');
          button.setAttribute('aria-pressed', 'false');
        });
        if (!obj) return;
        const names = {
          rect: 'Retângulo',
          circle: 'Círculo',
          triangle: 'Triângulo',
          line: 'Linha',
          image: 'Imagem',
          group: 'Grupo',
          activeSelection: 'Vários elementos'
        };
        $('selectionLabel').textContent = isText ? 'Texto selecionado' : (names[obj.type] || 'Elemento selecionado');
        $('selectedExcerpt').textContent = isText ? String(obj.text || 'Caixa vazia').replace(/\s+/g, ' ').slice(0, 65) :
          'Arraste no certificado para posicionar';
        if (!isText) return;
        $('textBoxWidth').value = Math.round(obj.sicadBoxWidth || obj.width);
        $('textMaxHeight').value = Number(obj.sicadMaxHeight) || 0;
        $('heightLimitSummary').textContent = Number(obj.sicadMaxHeight) > 0 ?
          'Limite de altura: ' + Number(obj.sicadMaxHeight) + ' px' : 'Limite de altura (opcional)';
        $('wrapToMargin').checked = !!obj.sicadWrapToMargin;
        $('fontSize').value = Number(Number(obj.fontSize).toFixed(2));
        $('lineHeight').value = Number(Number(obj.lineHeight || 1.16).toFixed(2));
        $('textAlignmentInfo').textContent = ALIGN_LABELS[alignmentChoice(obj.textAlign)];
        // Modelos anteriores podem usar uma fonte que não consta das opções iniciais.
        if (!Array.from($('fontFamily').options).some(opt => opt.value === obj.fontFamily)) {
          $('fontFamily').add(new Option(obj.fontFamily, obj.fontFamily));
        }
        $('fontFamily').value = obj.fontFamily;
        try {
          $('fontColor').value = '#' + new fabric.Color(obj.fill || '#000000').toHex();
        } catch (_) {
          /* cor complexa */ }
        const states = {
          bold: obj.fontWeight === 'bold' || Number(obj.fontWeight) >= 600,
          italic: obj.fontStyle === 'italic',
          underline: !!obj.underline
        };
        Object.entries(states).forEach(([key, on]) => {
          const button = $(key + 'Button');
          button.setAttribute('aria-pressed', String(on));
          button.classList.toggle('is-active', on);
        });
      }

      // A seleção do Fabric usa índices de grafemas; o textarea nativo usa UTF-16.
      const graphemes = text => fabric.util.string.graphemeSplit(String(text || ''));

      function captureTextSelection() {
        const obj = canvas.getActiveObject();
        savedSelection = isTextObject(obj) && obj.isEditing ?
          {
            obj,
            start: obj.selectionStart,
            end: obj.selectionEnd,
            text: obj.text
          } : null;
      }

      function clampIndex(value, maximum, fallback) {
        return Number.isFinite(Number(value)) ? Math.max(0, Math.min(maximum, Math.trunc(Number(value)))) : fallback;
      }
      /**
       * insertChars altera o objeto, mas não o valor do campo que recebe as teclas.
       * Sem esta sincronização, a próxima tecla restaura o texto anterior à tag.
       * Não executar durante composição IME nem substituir o handler onInput do Fabric.
       */
      function focusTextAt(obj, start, end = start) {
        if (!obj.isEditing) obj.enterEditing();
        const chars = graphemes(obj.text);
        start = clampIndex(start, chars.length, chars.length);
        end = clampIndex(end, chars.length, start);
        obj.selectionStart = Math.min(start, end);
        obj.selectionEnd = Math.max(start, end);
        obj.cursorOffsetCache = {};
        const textarea = obj.hiddenTextarea;
        if (textarea) {
          textarea.value = obj.text; // Correção essencial: sincroniza CONTEÚDO, não só seleção.
          const utf16Start = chars.slice(0, obj.selectionStart).join('').length;
          const utf16End = chars.slice(0, obj.selectionEnd).join('').length;
          textarea.setSelectionRange(utf16Start, utf16End);
          try {
            textarea.focus({
              preventScroll: true
            });
          } catch (_) {
            textarea.focus();
          }
          textarea.setSelectionRange(utf16Start, utf16End);
          if (typeof obj.updateTextareaPosition === 'function') obj.updateTextareaPosition();
        }
        if (typeof obj.initDelayedCursor === 'function') obj.initDelayedCursor(true);
        canvas.requestRenderAll();
      }

      function insertTag(tag) {
        if (!allowedTags.has(tag) || isLoading) return;
        const active = canvas.getActiveObject();
        if (active && active.inCompositionMode) {
          toast('Conclua a digitação atual antes de inserir a tag.');
          return;
        }
        if (isTextObject(active) && !$('tagSeparate').checked) {
          const chars = graphemes(active.text);
          const selection = savedSelection && savedSelection.obj === active && savedSelection.text === active.text ?
            savedSelection : null;
          const editing = !!selection || active.isEditing;
          const start = editing ? clampIndex(selection ? selection.start : active.selectionStart, chars.length, chars.length) : chars.length;
          const end = editing ? Math.max(start, clampIndex(selection ? selection.end : active.selectionEnd, chars.length, start)) : start;
          const prefix = !editing && chars.length && !/\s$/.test(active.text) ? ' ' : '';
          const inserted = prefix + tag;
          active.insertChars(inserted, undefined, start, end);
          configureFixedWrap(active);
          focusTextAt(active, start + graphemes(inserted).length);
          active.fire('changed');
          canvas.fire('text:changed', {
            target: active
          });
        } else {
          const widths = {
            '{{NOME}}': 360,
            '{{ATIVIDADE}}': 420,
            '{{CODIGO}}': 360
          };
          const width = Math.min(widths[tag] || 340, safeArea.right - safeArea.left);
          const text = new fabric.Textbox(tag, {
            left: safeArea.left,
            top: Math.max(safeArea.top, canvas.height * .25),
            width,
            sicadBoxWidth: width,
            fontSize: 24,
            fontFamily: 'Arial',
            fill: '#000000',
            name: 'tag',
            dataTipo: 'tag',
            sicadTag: tag,
            textAlign: 'center',
            editable: true
          });
          addObject(text);
          focusTextAt(text, graphemes(tag).length);
        }
        // Não deixar a seleção antiga substituir uma tag na inserção seguinte.
        savedSelection = null;
        syncTextControls();
        showLayoutWarnings();
      }

      function addText() {
        if (isLoading) return;
        const width = safeArea.right - safeArea.left;
        const text = new fabric.Textbox('Digite aqui', {
          left: safeArea.left,
          top: Math.max(safeArea.top, canvas.height * .25),
          width,
          sicadBoxWidth: width,
          fontSize: 24,
          fontFamily: 'Arial',
          fill: '#000000',
          textAlign: 'left',
          editable: true
        });
        addObject(text);
        // Só o texto inicial de exemplo é selecionado; duplo clique usa o comportamento nativo.
        focusTextAt(text, 0, graphemes(text.text).length);
      }

      function addCertificateParagraph() {
        if (isLoading) return;
        const width = safeArea.right - safeArea.left;
        const text = new fabric.Textbox(
          'Certificamos que {{NOME}} participou da atividade {{ATIVIDADE}}, com carga horária de {{CARGA_HORARIA}} horas.', {
            left: safeArea.left,
            top: Math.max(safeArea.top, canvas.height * .32),
            width,
            sicadBoxWidth: width,
            fontSize: 24,
            fontFamily: 'Arial',
            fill: '#000000',
            textAlign: 'justify-left',
            lineHeight: 1.25,
            editable: true
          });
        addObject(text);
        focusTextAt(text, graphemes(text.text).length);
      }

      function editSelectedText() {
        const obj = canvas.getActiveObject();
        if (!isTextObject(obj) || isLoading || obj.inCompositionMode) return;
        const end = graphemes(obj.text).length;
        focusTextAt(obj, obj.isEditing ? obj.selectionStart : end, obj.isEditing ? obj.selectionEnd : end);
      }

      function setTextAlignment(value) {
        if (!Object.prototype.hasOwnProperty.call(ALIGN_LABELS, value)) return;
        const obj = canvas.getActiveObject();
        if (!isTextObject(obj) || isLoading) return;
        if (obj.inCompositionMode) {
          toast('Conclua a digitação atual antes de alterar o alinhamento.');
          return;
        }
        // Mudar o alinhamento não seleciona nem substitui o conteúdo da caixa.
        const editing = obj.isEditing;
        const start = obj.selectionStart, end = obj.selectionEnd;
        updateText('textAlign', editorAlignment(value));
        if (editing) focusTextAt(obj, start, end);
      }

      function toggleFocusMode() {
        focusMode = !focusMode;
        document.body.classList.toggle('focus-mode', focusMode);
        $('focusModeButton').setAttribute('aria-pressed', String(focusMode));
        $('focusModeLabel').textContent = focusMode ? 'Mostrar painéis' : 'Ampliar área';
        $('focusModeButton').title = focusMode ? 'Voltar às ferramentas e propriedades' : 'Ocultar os painéis laterais sem alterar o certificado';
        // Somente visualização: não altera JSON, fonte, margens ou estado de salvamento.
        requestAnimationFrame(applyZoom);
      }

      function updateText(property, value) {
        const obj = canvas.getActiveObject();
        if (!isTextObject(obj) || isLoading) return;
        if (property === 'fontSize' || property === 'lineHeight') {
          value = Number(value);
          const valid = Number.isFinite(value) && (property === 'fontSize' ? value >= 1 && value <= 400 : value >= .8 && value <= 3);
          if (!valid) {
            toast(property === 'fontSize' ? 'Use uma fonte entre 1 e 400 px.' : 'Use entrelinhas de 0,8 a 3.', 'error');
            syncTextControls();
            return;
          }
        }
        // Preserva a aplicação de cor a um trecho selecionado do editor anterior.
        if (property === 'fill' && obj.isEditing && obj.selectionStart !== obj.selectionEnd && obj.setSelectionStyles) {
          obj.setSelectionStyles({
            fill: value
          });
        } else obj.set(property, value);
        configureFixedWrap(obj);
        canvas.requestRenderAll();
        syncTextControls();
        showLayoutWarnings();
        markDirty();
      }

      function toggleStyle(style) {
        const obj = canvas.getActiveObject();
        if (!isTextObject(obj)) return;
        if (style === 'bold') updateText('fontWeight', obj.fontWeight === 'bold' || Number(obj.fontWeight) >= 600 ? 'normal' : 'bold');
        if (style === 'italic') updateText('fontStyle', obj.fontStyle === 'italic' ? 'normal' : 'italic');
        if (style === 'underline') updateText('underline', !obj.underline);
      }

      function updateBoxWidth(value) {
        const obj = canvas.getActiveObject(),
          width = Number(value);
        if (!isTextObject(obj)) return;
        if (!Number.isFinite(width) || width < 1) {
          toast('A largura deve ser maior que zero.', 'error');
          syncTextControls();
          return;
        }
        obj.set({
          sicadBoxWidth: width,
          sicadWrapToMargin: false
        });
        configureFixedWrap(obj);
        canvas.requestRenderAll();
        syncTextControls();
        showLayoutWarnings();
        markDirty();
      }

      function updateMaxHeight(value) {
        const obj = canvas.getActiveObject(),
          height = Number(value);
        if (!isTextObject(obj)) return;
        if (!Number.isFinite(height) || height < 0) {
          toast('Use 0 ou uma altura positiva.', 'error');
          syncTextControls();
          return;
        }
        obj.set('sicadMaxHeight', height);
        syncTextControls();
        showLayoutWarnings();
        markDirty();
      }

      function updateWrapToMargin(checked) {
        const obj = canvas.getActiveObject();
        if (!isTextObject(obj)) return;
        obj.set('sicadWrapToMargin', checked);
        configureFixedWrap(obj);
        syncTextControls();
        canvas.requestRenderAll();
        showLayoutWarnings();
        markDirty();
      }

      function syncMarginInputs() {
        const values = {
          marginLeft: 100 * safeArea.left / canvas.width,
          marginRight: 100 * (canvas.width - safeArea.right) / canvas.width,
          marginTop: 100 * safeArea.top / canvas.height,
          marginBottom: 100 * (canvas.height - safeArea.bottom) / canvas.height
        };
        Object.entries(values).forEach(([id, value]) => {
          $(id).value = Number(value.toFixed(2));
        });
      }

      function updateSafeArea() {
        const ids = ['marginLeft', 'marginRight', 'marginTop', 'marginBottom'];
        if (ids.some(id => $(id).value === '' || !Number.isFinite(Number($(id).value)) || Number($(id).value) < 0 || Number($(id).value) > 45)) {
          toast('Informe margens entre 0% e 45%.', 'error');
          syncMarginInputs();
          return;
        }
        const n = id => Number($(id).value) / 100;
        const area = {
          left: canvas.width * n('marginLeft'),
          top: canvas.height * n('marginTop'),
          right: canvas.width * (1 - n('marginRight')),
          bottom: canvas.height * (1 - n('marginBottom'))
        };
        if (!validSafeArea(area, canvas.width, canvas.height)) {
          toast('As margens precisam deixar uma área útil positiva.', 'error');
          syncMarginInputs();
          return;
        }
        safeArea = area;
        drawSafeGuide();
        updateAllWrapping();
        syncTextControls();
        markDirty();
      }

      function drawSafeGuide() {
        $('safeOverlay').setAttribute('viewBox', `0 0 ${canvas.width} ${canvas.height}`);
        $('safeOverlay').style.display = $('showGuides').checked ? '' : 'none';
        Object.entries({
            x: safeArea.left,
            y: safeArea.top,
            width: safeArea.right - safeArea.left,
            height: safeArea.bottom - safeArea.top
          })
          .forEach(([k, v]) => $('safeRect').setAttribute(k, v));
      }

      function applyZoom() {
        const padding = getComputedStyle($('board'));
        const available = Math.max(100, $('board').clientWidth - parseFloat(padding.paddingLeft) - parseFloat(padding.paddingRight));
        viewScale = zoomMode === 'fit' ? Math.min(1, available / canvas.width) : Number(zoomMode);
        const width = Math.max(1, Math.round(canvas.width * viewScale)),
          height = Math.max(1, Math.round(canvas.height * viewScale));
        // cssOnly preserva o espaço lógico utilizado pelo JSON, pelas fontes e pelo PDF.
        canvas.setDimensions({
          width: width + 'px',
          height: height + 'px'
        }, {
          cssOnly: true
        });
        $('canvasStage').style.width = width + 'px';
        $('canvasStage').style.height = height + 'px';
        canvas.calcOffset();
        canvas.requestRenderAll();
        const obj = canvas.getActiveObject();
        if (obj && obj.isEditing && typeof obj.updateTextareaPosition === 'function') obj.updateTextareaPosition();
        drawSafeGuide();
        refreshInfo();
      }

      function stepZoom(direction) {
        const values = [.5, .75, 1, 1.25, 1.5];
        zoomMode = String(direction > 0 ? values.find(v => v > viewScale + .01) || 1.5 : [...values].reverse().find(v => v < viewScale - .01) || .5);
        $('zoomSelect').value = zoomMode;
        applyZoom();
      }

      function switchTab(name, focus = false) {
        ['insert', 'models'].forEach(tab => {
          const active = name === tab,
            button = tab === 'insert' ? $('tabInsert') : $('tabTemplates');
          button.setAttribute('aria-selected', String(active));
          button.tabIndex = active ? 0 : -1;
          $(tab === 'insert' ? 'insertPanel' : 'modelsPanel').hidden = !active;
          if (active && focus) button.focus();
        });
      }

      function showModels() {
        switchTab('models', true);
        $('tabTemplates').scrollIntoView({
          behavior: 'smooth',
          block: 'nearest'
        });
      }

      function syncTemplateCards() {
        document.querySelectorAll('[data-template]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.template === selectedTemplateSrc)));
      }

      function setTemplate(src) {
        if (isLoading) return;
        const request = ++modelRequest;
        $('loadMask').hidden = false;
        $('loadMaskText').textContent = 'Carregando modelo…';
        fabric.Image.fromURL(src, (img, error) => {
          if (request !== modelRequest) return;
          $('loadMask').hidden = true;
          if (error || !img || !img.width || !img.height) {
            toast('Não foi possível carregar o modelo. Verifique o acesso à imagem e o CORS.', 'error');
            return;
          }
          // Trocar o fundo não apaga os textos existentes.
          canvas.getObjects().filter(isBackground).forEach(obj => canvas.remove(obj));
          if (editableObjects().length === 0) {
            const w = 800,
              h = Math.max(1, Math.round(w * img.height / img.width));
            canvas.setDimensions({
              width: w,
              height: h
            });
            safeArea = defaultSafeArea(w, h);
          }
          selectedTemplateSrc = src;
          canvas.setBackgroundImage(img, () => canvas.requestRenderAll(), {
            scaleX: canvas.width / img.width,
            scaleY: canvas.height / img.height,
            left: 0,
            top: 0,
            originX: 'left',
            originY: 'top'
          });
          syncMarginInputs();
          updateAllWrapping();
          syncTemplateCards();
          applyZoom();
          markDirty();
        }, {
          crossOrigin: 'anonymous'
        });
      }

      function addShape(type) {
        if (isLoading) return;
        const color = $('shapeColor').value;
        const options = {
          left: safeArea.left + 25,
          top: safeArea.top + 25,
          fill: $('noFill').checked ? '' : color,
          stroke: color,
          strokeWidth: 2
        };
        const shape = type === 'rect' ? new fabric.Rect({
            ...options,
            width: 100,
            height: 100
          }) :
          type === 'circle' ? new fabric.Circle({
            ...options,
            radius: 50
          }) :
          type === 'triangle' ? new fabric.Triangle({
            ...options,
            width: 100,
            height: 100
          }) : null;
        if (shape) addObject(shape);
      }

      function addLine(type) {
        if (isLoading) return;
        const color = $('shapeColor').value,
          dashed = type === 'dashed' || type === 'dashedArrow';
        const line = new fabric.Line([0, 0, 150, 0], {
          left: safeArea.left + 25,
          top: safeArea.top + 45,
          stroke: color,
          strokeWidth: 3,
          strokeDashArray: dashed ? [10, 5] : null
        });
        if (type === 'arrow' || type === 'dashedArrow') {
          const triangle = new fabric.Triangle({
            left: safeArea.left + 175,
            top: safeArea.top + 46.5,
            originX: 'center',
            originY: 'center',
            angle: 90,
            width: 15,
            height: 15,
            fill: color
          });
          addObject(new fabric.Group([line, triangle]));
        } else addObject(line);
      }

      function updateShapeStyle() {
        const active = canvas.getActiveObject();
        if (!active || isTextObject(active)) return;
        const color = $('shapeColor').value;

        function change(obj) {
          if (obj.type === 'image' || isTextObject(obj) || isBackground(obj)) return;
          if (obj.getObjects) {
            obj.getObjects().forEach(change);
            obj.dirty = true;
          } else {
            obj.set('stroke', color);
            if (obj.type !== 'line') obj.set('fill', $('noFill').checked ? '' : color);
          }
        }
        change(active);
        canvas.requestRenderAll();
        markDirty();
      }

      function organize(front) {
        const selected = canvas.getActiveObjects().filter(obj => !isBackground(obj));
        if (!selected.length) return;
        const obj = canvas.getActiveObject();
        if (obj && obj.isEditing) obj.exitEditing();
        (front ? selected : [...selected].reverse()).forEach(item => front ? item.bringToFront() : item.sendToBack());
        canvas.getObjects().filter(isBackground).forEach(item => item.sendToBack());
        canvas.requestRenderAll();
        markDirty();
      }

      function deleteSelected() {
        const objects = canvas.getActiveObjects().filter(obj => !isBackground(obj));
        if (!objects.length) return;
        objects.forEach(obj => {
          if (obj.isEditing) obj.exitEditing();
        });
        canvas.discardActiveObject();
        objects.forEach(obj => canvas.remove(obj));
        savedSelection = null;
        canvas.requestRenderAll();
        syncTextControls();
        showLayoutWarnings();
      }

      function duplicateSelected() {
        const active = canvas.getActiveObject();
        if (!active || isBackground(active)) return;
        if (active.type === 'activeSelection') {
          toast('Selecione um elemento por vez para duplicar.');
          return;
        }
        if (active.isEditing) active.exitEditing();
        active.clone(copy => {
          if (isLoading) return;
          copy.set({
            left: Number(copy.left) + 16,
            top: Number(copy.top) + 16
          });
          addObject(copy);
          showLayoutWarnings();
        }, EXTRA_PROPERTIES);
      }

      function exportImage(multiplier) {
        // Fabric 5 exporta em outro canvas, sem desenhar os controles/contextTop.
        // NÃO sair de edição nem descartar a seleção: isso faria o autosave roubar o cursor.
        const original = {
          width: canvas.width, height: canvas.height,
          viewportTransform: canvas.viewportTransform,
          interactive: canvas.interactive, enableRetinaScaling: canvas.enableRetinaScaling,
          contextTop: canvas.contextTop
        };
        try {
          const image = canvas.toDataURL({ format: 'jpeg', quality: .95, multiplier,
            enableRetinaScaling: false });
          if (!image || !image.startsWith('data:image/jpeg;base64,')) {
            throw new Error('Não foi possível gerar a imagem do modelo. Verifique suas dimensões.');
          }
          return image;
        } finally {
          // Restaura também quando um renderizador ou uma imagem lança uma exceção.
          Object.assign(canvas, original);
          canvas.calcViewportBoundaries();
          canvas.requestRenderAll();
        }
      }

      function download() {
        if (isLoading) return;
        try {
          const link = document.createElement('a');
          link.href = exportImage(3);
          link.download = 'modelo_certificado.jpeg';
          document.body.append(link);
          link.click();
          link.remove();
          toast('Imagem do modelo exportada. As tags serão substituídas na emissão do certificado.');
        } catch (_) {
          toast('Não foi possível exportar. Verifique se as imagens utilizadas permitem CORS.', 'error');
        }
      }

      function canvasSnapshot() {
        const state = serializeTextAlignment(canvas.toJSON(EXTRA_PROPERTIES));
        function restoreEditingProperties(serialized, live) {
          if (isTextObject(live) && live.isEditing && live._savedProps) {
            ['hasControls', 'borderColor', 'lockMovementX', 'lockMovementY', 'hoverCursor', 'selectable'].forEach(key => {
              if (Object.prototype.hasOwnProperty.call(serialized, key) &&
                  Object.prototype.hasOwnProperty.call(live._savedProps, key)) {
                serialized[key] = live._savedProps[key];
              }
            });
          }
          // Estado de cursor não faz parte do documento.
          delete serialized.isEditing;
          if (Array.isArray(serialized.objects) && live.getObjects) {
            const children = live.getObjects().filter(obj => !obj.excludeFromExport);
            serialized.objects.forEach((obj, i) => { if (children[i]) restoreEditingProperties(obj, children[i]); });
          }
        }
        const liveObjects = canvas.getObjects().filter(obj => !obj.excludeFromExport);
        (state.objects || []).forEach((obj, i) => { if (liveObjects[i]) restoreEditingProperties(obj, liveObjects[i]); });

        state.canvasWidth = canvas.width;
        state.canvasHeight = canvas.height;
        state.sicadSafeArea = {
          ...safeArea
        };
        state.sicadLayoutVersion = VERSION;
        return state;
      }
      class SaveRequestError extends Error {
        constructor(message, retryable = false) {
          super(message);
          this.name = 'SaveRequestError';
          this.retryable = retryable;
        }
      }

      async function requestJSON(url, options = {}) {
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 45000);
        try {
          const response = await fetch(url, { ...options, signal: controller.signal, cache: 'no-store' });
          // O prazo cobre os cabeçalhos E a leitura do corpo da resposta.
          const raw = await response.text();
          const retryable = response.status === 408 || response.status === 429 || response.status >= 500;
          let data;
          try { data = JSON.parse(raw); }
          catch (_) {
            throw new SaveRequestError('O servidor não retornou JSON válido. Verifique sua conexão, sessão e o endpoint configurado.', retryable);
          }
          if (!response.ok || !data || !data.success) {
            throw new SaveRequestError((data && data.message) || 'A operação não foi concluída pelo servidor.', retryable);
          }
          return data;
        } catch (error) {
          if (error instanceof SaveRequestError) throw error;
          if (error.name === 'AbortError') {
            throw new SaveRequestError('O servidor demorou para responder. O salvamento ainda não foi confirmado.', true);
          }
          throw new SaveRequestError('Falha de conexão ao salvar ou carregar o modelo. Mantenha a página aberta e verifique sua conexão.', true);
        } finally {
          window.clearTimeout(timeout);
        }
      }

      async function saveCanvas({ automatic = false } = {}) {
        clearAutosaveTimer();
        if (isLoading || isSaving) return; // As revisões pendentes serão retomadas no finally.
        if (!hasActivity() || !initialLoadVerified) {
          pendingSaveState();
          if (!automatic) toast(!hasActivity() ? 'Abra o editor com um atividade_id válido na URL.' :
            'O modelo existente não pôde ser verificado. Recarregue a página antes de salvar.', 'error');
          return;
        }
        if (!automatic) {
          autosavePaused = false;
          retryAt = 0;
          retryAttempt = 0;
        }
        if (automatic && autosavePaused) return;
        if (automatic && retryAt > Date.now()) { scheduleAutosave(); return; }
        if (!dirty) {
          if (!automatic) toast('Não há alterações pendentes.', 'success');
          return;
        }
        if (!hasTemplate()) {
          pendingSaveState();
          if (!automatic) toast('Escolha uma imagem de modelo para iniciar o salvamento.', 'info');
          return;
        }
        if (!$('loadMask').hidden || editingGestureInProgress()) {
          scheduleAutosave();
          return;
        }
        isSaving = true;
        syncUnloadGuard();
        $('saveButton').disabled = true;
        $('saveButtonText').textContent = 'Salvando…';
        setSaveState('saving', 'Salvando…', 'Aguardando confirmação do servidor. Você pode continuar editando.');
        let shouldContinue = false, requestSent = false;
        try {
          if (document.fonts && document.fonts.ready) await document.fonts.ready;
          // O usuário pode ter iniciado outra interação durante o await.
          if (!$('loadMask').hidden || editingGestureInProgress()) {
            shouldContinue = true;
            return;
          }
          showLayoutWarnings();
          if (layoutWarnings(editableObjects(), safeArea).length) {
            setSaveState('waiting', 'Pendente · revise o layout', 'Há texto fora da área útil ou da altura reservada. Ajuste o bloco; o salvamento será retomado automaticamente.');
            if (!automatic) toast('Revise os blocos fora da área útil antes de salvar.', 'error');
            return;
          }
          // Captura síncrona: JSON e prévia pertencem à MESMA revisão.
          // Não chama exitEditing(), discardActiveObject() ou focus().
          const jsonString = JSON.stringify(canvasSnapshot());
          if (jsonString === lastSavedJSON) {
            dirty = false;
            pendingSince = 0;
            lastSaveFailure = null;
            retryAttempt = 0;
            retryAt = 0;
            setSaveState('saved', 'Todas as alterações salvas');
            if (!automatic) toast('O modelo já está atualizado.', 'success');
            return;
          }
          const previewImage = exportImage(3);
          const savedRevision = revision;
          pendingSince = 0;
          requestSent = true;
          await requestJSON(API_BASE + 'salvarTemplate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              json: jsonString,
              atividade_id: atividade_id,
              imagem_src: selectedTemplateSrc,
              canvas_image: previewImage
            })
          });
          lastSavedJSON = jsonString;
          lastSaveFailure = null;
          retryAttempt = 0;
          retryAt = 0;
          autosavePaused = false;
          if (revision === savedRevision) {
            dirty = false;
            pendingSince = 0;
            const time = new Date().toLocaleTimeString('pt-BR');
            setSaveState('saved', 'Todas as alterações salvas', 'Última confirmação do servidor: ' + time + '. Salvamento automático ativo.');
          } else {
            // Nunca marca como salva uma alteração feita DEPOIS de iniciar a requisição.
            shouldContinue = true;
          }
          if (!automatic) toast(dirty ? 'Versão salva. As alterações mais recentes serão salvas automaticamente.' : 'Modelo salvo com sucesso.', 'success');
        } catch (error) {
          dirty = true;
          // Sem confirmação, a versão remota é incerta: não deduplicar usando a base anterior.
          if (requestSent) lastSavedJSON = null;
          const message = error.message || 'Não foi possível salvar. Verifique as imagens e tente novamente.';
          const firstFailure = !lastSaveFailure;
          lastSaveFailure = { message, retryable: !!error.retryable };
          if (lastSaveFailure.retryable && retryAttempt < AUTOSAVE_RETRY_MS.length) {
            retryAt = Date.now() + AUTOSAVE_RETRY_MS[retryAttempt++];
            shouldContinue = true;
          } else {
            autosavePaused = true;
            retryAt = 0;
          }
          if (!automatic || firstFailure) {
            toast(message + ' As alterações ainda não foram confirmadas. Mantenha esta página aberta.', 'error', 9000);
          }
        } finally {
          isSaving = false;
          syncUnloadGuard();
          $('saveButton').disabled = isLoading || !hasActivity() || !initialLoadVerified;
          $('saveButtonText').textContent = autosavePaused ? 'Tentar salvar' : 'Salvar agora';
          if (lastSaveFailure) pendingSaveState();
          if (dirty && shouldContinue) scheduleAutosave();
        }
      }

      function setLoading(on) {
        isLoading = on;
        $('loadMask').hidden = !on;
        $('loadMaskText').textContent = 'Carregando modelo salvo…';
        document.querySelectorAll('.left-panel, .right-panel, #toolbar').forEach(el => {
          el.inert = on;
        });
        $('saveButton').disabled = on || isSaving || !hasActivity() || !initialLoadVerified;
      }
      async function loadCanvas() {
        if (!atividade_id || !/^[1-9][0-9]*$/.test(atividade_id)) {
          pendingSaveState();
          $('saveButton').disabled = true;
          if (atividade_id) toast('O identificador da atividade é inválido. O salvamento está indisponível.', 'error');
          return;
        }
        $('activityBadge').textContent = 'Atividade #' + atividade_id;
        setLoading(true);
        setSaveState('loading', 'Carregando modelo…');
        try {
          const data = await requestJSON(API_BASE + 'getTemplate.php?atividade_id=' + encodeURIComponent(atividade_id));
          if (!data.template || !data.template.json) {
            initialLoadVerified = true;
            lastSavedJSON = JSON.stringify(canvasSnapshot());
            setSaveState('ready', 'Salvamento automático ativo', 'Escolha um modelo e comece a editar.');
            return;
          }
          let json = data.template.json;
          for (let i = 0; i < 4 && typeof json === 'string'; i++) json = JSON.parse(json);
          if (!json || !Array.isArray(json.objects)) throw new Error('O modelo salvo contém um JSON inválido.');
          const w = Number(json.canvasWidth) || 800,
            h = Number(json.canvasHeight) || 600;
          if (![w, h].every(n => Number.isFinite(n) && n > 0 && n <= 16000)) throw new Error('O modelo salvo possui dimensões inválidas.');
          canvas.setDimensions({
            width: w,
            height: h
          });
          safeArea = validSafeArea(json.sicadSafeArea, w, h) ?
            Object.fromEntries(['left', 'top', 'right', 'bottom'].map(k => [k, Number(json.sicadSafeArea[k])])) : defaultSafeArea(w, h);
          normalizeTemplateJSON(json);
          await new Promise(resolve => canvas.loadFromJSON(json, resolve));
          function verifyLoadedItems(expected, actual) {
            if (expected.length !== actual.length) throw new Error('Um elemento do modelo não pôde ser carregado.');
            expected.forEach((item, i) => {
              if (!actual[i] || (item.type === 'image' && (!actual[i].width || !actual[i].height))) {
                throw new Error('Uma imagem do modelo não pôde ser carregada.');
              }
              if (Array.isArray(item.objects)) verifyLoadedItems(item.objects, actual[i].getObjects ? actual[i].getObjects() : []);
            });
          }
          verifyLoadedItems(json.objects, canvas.getObjects());
          ['backgroundImage', 'overlayImage'].forEach(key => {
            if (json[key] && (!canvas[key] || !canvas[key].width || !canvas[key].height)) {
              throw new Error('A imagem de fundo ou de sobreposição não pôde ser carregada.');
            }
          });

          if (document.fonts && document.fonts.ready) await document.fonts.ready;
          if (!canvas.backgroundColor) canvas.backgroundColor = '#ffffff';
          canvas.getObjects().forEach(styleControls);
          updateAllWrapping();
          const legacyBackground = canvas.getObjects().find(isBackground);
          selectedTemplateSrc = canvas.backgroundImage ? canvas.backgroundImage.getSrc() :
            legacyBackground ? legacyBackground.modeloSrc || legacyBackground.getSrc() : null;
          initialLoadVerified = true;
          lastSavedJSON = JSON.stringify(canvasSnapshot());
          dirty = false;
          syncUnloadGuard();
          setSaveState('saved', 'Modelo carregado', 'O salvamento automático está ativo.');
        } catch (error) {
          initialLoadVerified = false;
          setSaveState('error', 'Falha ao carregar · recarregue', 'O salvamento foi bloqueado para não sobrescrever um modelo que não foi carregado corretamente.');
          toast((error.message || 'Não foi possível carregar o modelo.') + ' Recarregue a página antes de editar; o salvamento está bloqueado por segurança.', 'error', 10000);
        } finally {
          setLoading(false);
          syncMarginInputs();
          syncTextControls();
          syncTemplateCards();
          applyZoom();
          refreshInfo();
        }
      }

      // Eventos do canvas. Sem selectAll forçado no duplo clique.
      canvas.on('object:added', e => {
        styleControls(e.target);
        if (!applyingWrap && !isLoading) configureFixedWrap(e.target);
        markDirty();
      });
      canvas.on('object:removed', () => {
        savedSelection = null;
        markDirty();
        showLayoutWarnings();
      });
      canvas.on('object:modified', e => {
        if (isTextObject(e.target)) {
          e.target.sicadBoxWidth = e.target.width;
          configureFixedWrap(e.target);
        }
        syncTextControls();
        showLayoutWarnings();
        markDirty();
      });
      canvas.on('text:changed', e => {
        // O input nativo já atualizou o objeto. Não reescrever o textarea a cada tecla.
        if (!e.target.inCompositionMode) configureFixedWrap(e.target);
        savedSelection = null;
        canvas.requestRenderAll();
        showLayoutWarnings();
        syncTextControls();
        markDirty();
      });
      ['selection:created', 'selection:updated', 'selection:cleared'].forEach(name => canvas.on(name, () => {
        savedSelection = null;
        syncTextControls();
      }));
      canvas.on('text:editing:exited', e => {
        configureFixedWrap(e.target);
        showLayoutWarnings();
        syncTextControls();
      });
      canvas.on('text:selection:changed', () => {
        savedSelection = null;
      });

      // Impede que o clique nos atalhos de tag roube a seleção do texto.
      document.querySelectorAll('[data-insert-tag]').forEach(button => {
        button.addEventListener('pointerdown', e => {
          captureTextSelection();
          if (e.button === 0) e.preventDefault();
        });
        button.addEventListener('focus', captureTextSelection);
        button.addEventListener('click', () => insertTag(button.dataset.insertTag));
      });
      $('tagSeparate').addEventListener('change', syncTagInstruction);
      document.querySelectorAll('[data-align]').forEach(button => {
        button.addEventListener('pointerdown', e => {
          if (e.button === 0) e.preventDefault();
        });
        button.addEventListener('click', () => setTextAlignment(button.dataset.align));
      });
      $('editTextButton').addEventListener('pointerdown', e => {
        if (e.button === 0) e.preventDefault();
      });
      document.querySelectorAll('[data-style]').forEach(button => {
        button.addEventListener('pointerdown', e => {
          if (e.button === 0) e.preventDefault();
        });
        button.addEventListener('click', () => toggleStyle(button.dataset.style));
      });
      const actions = {
        addText,
        paragraph: addCertificateParagraph,
        editText: editSelectedText,
        focusMode: toggleFocusMode,
        upload: () => $('imgLoader').click(),
        front: () => organize(true),
        back: () => organize(false),
        duplicate: duplicateSelected,
        delete: deleteSelected,
        download,
        save: saveCanvas,
        zoomIn: () => stepZoom(1),
        zoomOut: () => stepZoom(-1),
        showModels
      };
      document.querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => {
        const action = actions[button.dataset.action];
        if (action && !isLoading) action();
      }));
      document.querySelectorAll('[data-shape]').forEach(button => button.addEventListener('click', () => addShape(button.dataset.shape)));
      document.querySelectorAll('[data-line]').forEach(button => button.addEventListener('click', () => addLine(button.dataset.line)));
      document.querySelectorAll('[data-template]').forEach(button => button.addEventListener('click', () => setTemplate(button.dataset.template)));
      document.querySelectorAll('[data-tab]').forEach(button => {
        button.addEventListener('click', () => switchTab(button.dataset.tab));
        button.addEventListener('keydown', e => {
          if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) {
            e.preventDefault();
            switchTab(e.key === 'Home' ? 'insert' : e.key === 'End' ? 'models' : button.dataset.tab === 'insert' ? 'models' : 'insert', true);
          }
        });
      });
      ['fontFamily', 'fontSize', 'lineHeight'].forEach(id => $(id).addEventListener('change', e => updateText(id, e.target.value)));
      $('fontColor').addEventListener('change', e => updateText('fill', e.target.value));
      $('textBoxWidth').addEventListener('change', e => updateBoxWidth(e.target.value));
      $('textMaxHeight').addEventListener('change', e => updateMaxHeight(e.target.value));
      $('wrapToMargin').addEventListener('change', e => updateWrapToMargin(e.target.checked));
      ['marginLeft', 'marginRight', 'marginTop', 'marginBottom'].forEach(id => $(id).addEventListener('change', updateSafeArea));
      $('showGuides').addEventListener('change', drawSafeGuide);
      $('zoomSelect').addEventListener('change', e => {
        zoomMode = e.target.value;
        applyZoom();
      });
      $('shapeColor').addEventListener('change', updateShapeStyle);
      $('noFill').addEventListener('change', updateShapeStyle);
      $('imgLoader').addEventListener('change', e => {
        const file = e.target.files && e.target.files[0];
        e.target.value = '';
        if (!file || isLoading) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
          toast('Escolha uma imagem JPG, PNG ou WebP de até 10 MB.', 'error');
          return;
        }
        const reader = new FileReader();
        reader.onerror = () => toast('Não foi possível ler a imagem.', 'error');
        reader.onload = event => fabric.Image.fromURL(event.target.result, (img, error) => {
          if (error || !img || !img.width) {
            toast('A imagem está inválida ou não pôde ser carregada.', 'error');
            return;
          }
          img.scaleToWidth(Math.min(300, safeArea.right - safeArea.left));
          img.set({
            left: safeArea.left + 20,
            top: safeArea.top + 20
          });
          addObject(img);
        });
        reader.readAsDataURL(file);
      });
      document.addEventListener('keydown', e => {
        if (e.defaultPrevented || e.isComposing || isLoading) return;
        const active = canvas.getActiveObject(),
          key = e.key.toLowerCase();
        if ((e.ctrlKey || e.metaKey) && key === 's') {
          e.preventDefault();
          saveCanvas();
          return;
        }
        const input = e.target && (e.target.closest('input,textarea,select,[contenteditable="true"]'));
        if (input || (active && active.isEditing)) return; // Não apaga a caixa enquanto se digita.
        if (e.key === 'Escape' && focusMode) {
          e.preventDefault();
          toggleFocusMode();
          return;
        }
        if (e.target && e.target.closest('button,summary,a')) return; // Respeita navegação de teclado da interface.
        if (e.key === 'Delete' || e.key === 'Backspace') {
          if (active) {
            e.preventDefault();
            deleteSelected();
          }
        } else if ((e.ctrlKey || e.metaKey) && key === 'd' && active) {
          e.preventDefault();
          duplicateSelected();
        } else if (active && ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key) && !e.ctrlKey && !e.metaKey) {
          e.preventDefault();
          const step = e.shiftKey ? 10 : 1;
          active.set({
            left: active.left + (e.key === 'ArrowRight' ? step : e.key === 'ArrowLeft' ? -step : 0),
            top: active.top + (e.key === 'ArrowDown' ? step : e.key === 'ArrowUp' ? -step : 0)
          });
          active.setCoords();
          canvas.requestRenderAll();
          showLayoutWarnings();
          markDirty();
        }
      });
      // Antes de terminar um gesto, não salvar uma posição intermediária.
      canvas.on('mouse:down', () => { pointerOnCanvas = true; });
      canvas.on('mouse:up', () => { pointerOnCanvas = false; scheduleAutosave(); });
      ['pointerup', 'pointercancel', 'touchend'].forEach(name => document.addEventListener(name, () => {
        if (!pointerOnCanvas) return;
        pointerOnCanvas = false;
        // Aguarda a finalização do gesto pelo Fabric.
        window.setTimeout(() => scheduleAutosave(), 0);
      }));
      document.addEventListener('compositionend', () => {
        window.setTimeout(() => {
          const active = canvas.getActiveObject();
          if (isTextObject(active) && !active.inCompositionMode) configureFixedWrap(active);
          if (dirty) scheduleAutosave();
        }, 0);
      });
      window.addEventListener('online', () => {
        if (!dirty || (lastSaveFailure && !lastSaveFailure.retryable)) return;
        retryAttempt = 0;
        retryAt = 0;
        autosavePaused = false;
        scheduleAutosave(true);
      });
      // Melhor esforço enquanto a página ainda está viva. NÃO confirma salvamento ao fechar.
      document.addEventListener('visibilitychange', () => {
        if (dirty && document.visibilityState === 'hidden') scheduleAutosave(true);
      });
      const queueResize = () => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(applyZoom);
      };
      if (window.ResizeObserver) new ResizeObserver(queueResize).observe($('board'));
      window.addEventListener('resize', queueResize);
      syncMarginInputs();
      syncTextControls();
      refreshInfo();
      applyZoom();
      loadCanvas();
    })();
  </script>
</body>

</html>