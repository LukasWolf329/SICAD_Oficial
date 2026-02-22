import React, { createContext, useCallback, useContext, useMemo, useState } from "react";

type EventosModalCtx = {
  showEventos: boolean;
  openEventos: () => void;
  closeEventos: () => void;
};

const Ctx = createContext<EventosModalCtx | null>(null);

export function EventosModalProvider({ children }: { children: React.ReactNode }) {
  const [showEventos, setShowEventos] = useState(false);

  const openEventos = useCallback(() => setShowEventos(true), []);
  const closeEventos = useCallback(() => setShowEventos(false), []);

  const value = useMemo(
    () => ({ showEventos, openEventos, closeEventos }),
    [showEventos, openEventos, closeEventos]
  );

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useEventosModal() {
  const ctx = useContext(Ctx);
  if (!ctx) {
    throw new Error("useEventosModal precisa estar dentro de EventosModalProvider");
  }
  return ctx;
}