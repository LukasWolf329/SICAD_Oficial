import "../../../../style/global.css";

import React, { useEffect, useMemo, useState } from "react";
import { Pressable, ScrollView, Text, TextInput, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";

import { Mainframe } from "../../../../components/NavBar";
import { router } from "expo-router";
import { getLastEventoId, getLastEventoNome } from "@/app/utils/lastEvento";
import AsyncStorage from "@react-native-async-storage/async-storage";

type Certificado = {
  cod_certificado: number;
  codigo_validacao?: string | null;
  participante: string;
  email: string;
  status: number;
  atividade_id?: number;
  atividade?: string;
};

type ApiObject = Record<string, any>;

const API_BASE = "https://sicad.linceonline.com.br/controller";

const TABLE_MIN_WIDTH = 1180;
type IoniconName = React.ComponentProps<typeof Ionicons>["name"];

function parseApiResponse(raw: unknown) {
  if (typeof raw !== "string") return raw;

  const texto = raw.trim();

  try {
    return JSON.parse(texto);
  } catch { }

  const inicioObj = texto.indexOf("{");
  const inicioArr = texto.indexOf("[");

  let inicio = -1;

  if (inicioObj >= 0 && inicioArr >= 0) {
    inicio = Math.min(inicioObj, inicioArr);
  } else if (inicioObj >= 0) {
    inicio = inicioObj;
  } else if (inicioArr >= 0) {
    inicio = inicioArr;
  }

  if (inicio >= 0) {
    try {
      return JSON.parse(texto.slice(inicio));
    } catch {
      return null;
    }
  }

  return null;
}

async function postJsonSafe(url: string, body: unknown, signal?: AbortSignal): Promise<ApiObject> {
  const res = await fetch(url, {
    method: "POST",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
    signal,
  });

  const raw = await res.text();

  console.log("API URL:", url);
  console.log("API STATUS:", res.status);
  console.log("API CONTENT-TYPE:", res.headers.get("content-type"));
  console.log("API RAW:", JSON.stringify(raw));

  const data = parseApiResponse(raw);

  if (!res.ok) {
    const message = data && typeof data === "object" && "message" in data
      ? String((data as any).message)
      : raw;
    throw new Error(`HTTP ${res.status}: ${message}`);
  }

  if (!data || typeof data !== "object") {
    throw new Error(`Resposta inválida do servidor em ${url}`);
  }

  return data as ApiObject;
}

function extrairFilename(contentDisposition: string | null): string | null {
  if (!contentDisposition) return null;

  const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match?.[1]) {
    try {
      return decodeURIComponent(utf8Match[1].replace(/"/g, ""));
    } catch {
      return utf8Match[1].replace(/"/g, "");
    }
  }

  const filenameMatch = contentDisposition.match(/filename="?([^";]+)"?/i);
  return filenameMatch?.[1] ?? null;
}

async function postJsonForPdf(url: string, body: unknown): Promise<{ blob: Blob; filename?: string }> {
  const res = await fetch(url, {
    method: "POST",
    headers: {
      "Accept": "application/pdf, application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
  });

  const contentType = res.headers.get("content-type") ?? "";

  if (!res.ok || contentType.toLowerCase().includes("application/json")) {
    const raw = await res.text();
    const data = parseApiResponse(raw);
    const message = data && typeof data === "object" && "message" in data
      ? String((data as any).message)
      : raw || `HTTP ${res.status}`;
    throw new Error(message);
  }

  const filename =
    res.headers.get("x-certificado-filename") ||
    extrairFilename(res.headers.get("content-disposition")) ||
    undefined;

  return {
    blob: await res.blob(),
    filename,
  };
}

function sanitizarNomeArquivo(valor: string): string {
  return valor
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-zA-Z0-9._-]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .slice(0, 120) || "certificado";
}

function nomeArquivoCertificado(certificado: Certificado): string {
  const aluno = sanitizarNomeArquivo(certificado.participante || "aluno");
  const atividade = sanitizarNomeArquivo(certificado.atividade || "certificado");
  return `${aluno}_${atividade}.pdf`;
}

function baixarBlobNoNavegador(blob: Blob, filename: string): void {
  if (typeof window === "undefined" || typeof document === "undefined") {
    alert("O download automático está disponível na versão web. Abra esta tela pelo navegador.");
    return;
  }

  const url = window.URL.createObjectURL(blob);
  const link = document.createElement("a");

  link.href = url;
  link.download = filename;
  link.style.display = "none";

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  window.URL.revokeObjectURL(url);
}

function statusLabel(status: number): string {
  return status === 1 ? "Enviado" : "Não enviado";
}

function StatusBadge({ status }: { status: number }) {
  const enviado = status === 1;

  return (
    <View
      className={`rounded-lg px-3 py-1 ${enviado ? "bg-[#2192ff]" : "bg-[#ef4444]"}`}
      style={{ alignSelf: "flex-start", minWidth: 104 }}
    >
      <Text className="text-xs font-semibold color-white text-center" numberOfLines={1}>
        {statusLabel(status)}
      </Text>
    </View>
  );
}

function TopActionButton({
  label,
  loadingLabel,
  icon,
  loading,
  disabled,
  onPress,
  minWidth,
}: {
  label: string;
  loadingLabel: string;
  icon: IoniconName;
  loading: boolean;
  disabled: boolean;
  onPress: () => void;
  minWidth: number;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      className={`flex-row items-center justify-center rounded-lg px-3 py-2 ${disabled ? "bg-slate-300" : "bg-[#2192ff]"}`}
      style={{ minWidth, height: 44, flexShrink: 0 }}
    >
      <Ionicons name={icon} size={18} color="#fff" />
      <Text className="color-white ml-1 font-medium" numberOfLines={1}>
        {loading ? loadingLabel : label}
      </Text>
    </Pressable>
  );
}

function RowActionButton({
  label,
  loadingLabel,
  icon,
  loading,
  disabled,
  onPress,
  minWidth,
}: {
  label: string;
  loadingLabel: string;
  icon: IoniconName;
  loading: boolean;
  disabled: boolean;
  onPress: () => void;
  minWidth: number;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      className={`flex-row items-center justify-center rounded-lg px-3 py-1 ${disabled ? "bg-slate-300" : "bg-[#2192ff]"}`}
      style={{ minWidth, flexShrink: 0 }}
    >
      <Ionicons name={icon} size={17} color="#fff" />
      <Text className="color-white ml-1" numberOfLines={1}>
        {loading ? loadingLabel : label}
      </Text>
    </Pressable>
  );
}

function CertificadoLinha({
  certificado,
  enviando,
  baixando,
  onSend,
  onDownload,
}: {
  certificado: Certificado;
  enviando: boolean;
  baixando: boolean;
  onSend: () => void;
  onDownload: () => void;
}) {
  const ocupado = enviando || baixando;

  return (
    <View className="flex-row items-center border-b border-slate-200 px-4 py-3">
      <View style={{ flex: 1.7, minWidth: 180 }} className="pr-3">
        <Text className="font-medium dark:color-white" numberOfLines={1}>
          {certificado.participante || "-"}
        </Text>
      </View>

      <View style={{ flex: 1.8, minWidth: 210 }} className="pr-3">
        <Text className="color-slate-700 dark:color-white" numberOfLines={1}>
          {certificado.atividade || "-"}
        </Text>
      </View>

      <View style={{ flex: 2.2, minWidth: 260 }} className="pr-3">
        <Text className="color-slate-700 dark:color-white" numberOfLines={1}>
          {certificado.email || "-"}
        </Text>
      </View>

      <View style={{ width: 140 }} className="pr-3">
        <StatusBadge status={certificado.status} />
      </View>

      <View style={{ width: 280 }} className="flex-row items-center gap-2 justify-start">
        <RowActionButton
          onPress={onSend}
          disabled={ocupado}
          loading={enviando}
          icon="mail-outline"
          label="Enviar"
          loadingLabel="Enviando..."
          minWidth={110}
        />

        <RowActionButton
          onPress={onDownload}
          disabled={ocupado}
          loading={baixando}
          icon="download-outline"
          label="Baixar PDF"
          loadingLabel="Gerando..."
          minWidth={130}
        />
      </View>
    </View>
  );
}

export default function SendCerticate() {
  const [eventoId, setEventoId] = useState<number | null>(null);
  const [eventoNome, setEventoNome] = useState<string>("Evento");

  const [certificados, setCertificados] = useState<Certificado[]>([]);
  const [loadingEvento, setLoadingEvento] = useState(true);
  const [loadingCertificados, setLoadingCertificados] = useState(false);
  const [enviandoIds, setEnviandoIds] = useState<Record<number, boolean>>({});
  const [baixandoIds, setBaixandoIds] = useState<Record<number, boolean>>({});
  const [enviandoTodos, setEnviandoTodos] = useState(false);
  const [baixandoTodos, setBaixandoTodos] = useState(false);

  const [busca, setBusca] = useState("");

  const certificadosFiltrados = useMemo(() => {
    const termo = busca.trim().toLowerCase();

    if (!termo) return certificados;

    return certificados.filter((c) => {
      return (
        c.participante.toLowerCase().includes(termo) ||
        c.email.toLowerCase().includes(termo) ||
        (c.atividade ?? "").toLowerCase().includes(termo)
      );
    });
  }, [busca, certificados]);

  useEffect(() => {
    let alive = true;

    (async () => {
      try {
        const userId = await AsyncStorage.getItem("userId");
        const lastIdRaw = await getLastEventoId(userId);
        const lastId = Number(lastIdRaw);

        if (!alive) return;

        if (!lastId) {
          setEventoId(null);
          setEventoNome("Nenhum evento selecionado");
          setCertificados([]);
          return;
        }

        setEventoId(lastId);

        const json = await postJsonSafe(`${API_BASE}/page-org.php`, { evento_id: lastId });
        setEventoNome(json?.evento_nome ?? "Evento");
      } catch (err) {
        console.error("Erro ao carregar último evento:", err);
      } finally {
        if (alive) setLoadingEvento(false);
      }
    })();

    return () => {
      alive = false;
    };
  }, []);

  useEffect(() => {
    if (!eventoId) return;

    const controller = new AbortController();
    setLoadingCertificados(true);

    postJsonSafe(
      `${API_BASE}/get_certificado.php?debug=1&t=${Date.now()}`,
      { evento_id: eventoId, sincronizar: false },
      controller.signal
    )
      .then((data) => {
        const lista = Array.isArray(data) ? data : data?.certificados ?? [];

        setCertificados(
          lista.map((c: any) => ({
            cod_certificado: Number(c.cod_certificado),
            codigo_validacao: c.codigo_validacao ? String(c.codigo_validacao) : null,
            participante: String(c.participante ?? ""),
            email: String(c.email ?? ""),
            status: Number(c.status ?? 0),
            atividade_id: c.atividade_id ? Number(c.atividade_id) : undefined,
            atividade: c.atividade ? String(c.atividade) : undefined,
          }))
        );
      })
      .catch((err) => {
        if ((err as any)?.name !== "AbortError") {
          console.error("Erro ao carregar os certificados:", err);
          alert("Erro ao carregar os certificados");
        }
      })
      .finally(() => setLoadingCertificados(false));

    return () => controller.abort();
  }, [eventoId]);

  useEffect(() => {
    (async () => {
      const userId = await AsyncStorage.getItem("userId");
      const nome = await getLastEventoNome(userId);
      if (nome) setEventoNome(nome);
    })();
  }, []);

  const handleSendOne = async (certificado: Certificado) => {
    const codCertificado = certificado.cod_certificado;

    if (!codCertificado || enviandoIds[codCertificado] || baixandoIds[codCertificado]) return;

    setEnviandoIds((prev) => ({ ...prev, [codCertificado]: true }));

    try {
      const data = await postJsonSafe(`${API_BASE}/enviar_certificado.php`, {
        cod_certificado: codCertificado,
        modo: "email",
      });

      if (data.success) {
        setCertificados((prev) =>
          prev.map((c) =>
            c.cod_certificado === codCertificado
              ? { ...c, status: Number(data.status ?? 1) }
              : c
          )
        );
      } else {
        alert(data.message ?? "Falha ao enviar certificado");
      }
    } catch (err) {
      console.error("Erro ao enviar certificado:", err);
      alert((err as Error)?.message || "Falha ao enviar certificado");
    } finally {
      setEnviandoIds((prev) => {
        const copy = { ...prev };
        delete copy[codCertificado];
        return copy;
      });
    }
  };

  const handleDownloadOne = async (certificado: Certificado) => {
    const codCertificado = certificado.cod_certificado;

    if (!codCertificado || enviandoIds[codCertificado] || baixandoIds[codCertificado]) return;

    setBaixandoIds((prev) => ({ ...prev, [codCertificado]: true }));

    try {
      const { blob, filename } = await postJsonForPdf(`${API_BASE}/enviar_certificado.php`, {
        cod_certificado: codCertificado,
        modo: "download",
      });

      baixarBlobNoNavegador(blob, filename || nomeArquivoCertificado(certificado));
    } catch (err) {
      console.error("Erro ao baixar certificado:", err);
      alert((err as Error)?.message || "Falha ao baixar certificado");
    } finally {
      setBaixandoIds((prev) => {
        const copy = { ...prev };
        delete copy[codCertificado];
        return copy;
      });
    }
  };

  const handleSendAll = async () => {
    if (enviandoTodos || baixandoTodos || certificadosFiltrados.length === 0) return;

    setEnviandoTodos(true);

    try {
      for (const certificado of certificadosFiltrados) {
        await handleSendOne(certificado);
        await new Promise((resolve) => setTimeout(resolve, 250));
      }
      console.log("Envio para todos concluído");
    } catch (err) {
      console.error("Erro no envio para todos:", err);
      alert("Falha ao enviar todos os certificados");
    } finally {
      setEnviandoTodos(false);
    }
  };

  const handleDownloadAll = async () => {
    if (baixandoTodos || enviandoTodos || certificadosFiltrados.length === 0) return;

    setBaixandoTodos(true);

    try {
      for (const certificado of certificadosFiltrados) {
        await handleDownloadOne(certificado);
        await new Promise((resolve) => setTimeout(resolve, 250));
      }
    } catch (err) {
      console.error("Erro ao baixar todos os certificados:", err);
      alert("Falha ao baixar todos os certificados");
    } finally {
      setBaixandoTodos(false);
    }
  };

  const listaVazia = certificados.length === 0;
  const semResultadoBusca = certificados.length > 0 && certificadosFiltrados.length === 0;
  const acoesDesabilitadas = loadingCertificados || certificadosFiltrados.length === 0 || enviandoTodos || baixandoTodos;

  return (
    <ScrollView className="flex-1 dark:bg-black">
      <Mainframe name={eventoNome} photoUrl="evento.png" link="www.evento.com">
        <View className="px-8">
          <Text className="text-2xl dark:color-white">Certificados</Text>

          <View className="flex-row items-center justify-between my-2">
            <View className="flex-row items-center gap-2 mt-2">
              <Pressable
                onPress={() => router.push("./page")}
                className="w-min px-3 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"
              >
                <Ionicons name="add" size={22} />
                <Text>Criar</Text>
              </Pressable>

              <Pressable
                onPress={() => router.push("./send")}
                className="w-min px-3 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"
              >
                <Ionicons name="mail-outline" size={22} />
                <Text className="text-nowrap">Envio por E-mail</Text>
              </Pressable>

              <Pressable
                onPress={() => router.push("./settings")}
                className="w-min px-3 flex-row bg-[#9BEC00] rounded-lg justify-center items-center p-1"
              >
                <Ionicons name="settings-outline" size={22} />
                <Text>Configurações</Text>
              </Pressable>
            </View>
          </View>

          <View className="mt-4 border-b-2 border-slate-300 pb-3">
            <View className="flex-row items-center justify-between flex-wrap gap-2">
              <Text className="text-2xl dark:color-white">Enviar Certificados</Text>

              <View className="flex-row items-center justify-end flex-wrap gap-2">
                <TextInput
                  placeholder="Buscar"
                  value={busca}
                  onChangeText={setBusca}
                  className="bg-transparent border border-slate-400 rounded-lg px-3 py-2 color-slate-500 dark:color-white"
                  style={{ width: 220, height: 44, flexShrink: 0 }}
                />

                <TopActionButton
                  onPress={handleDownloadAll}
                  disabled={acoesDesabilitadas}
                  loading={baixandoTodos}
                  icon="download-outline"
                  label="Baixar todos"
                  loadingLabel="Gerando..."
                  minWidth={145}
                />

                <TopActionButton
                  onPress={handleSendAll}
                  disabled={acoesDesabilitadas}
                  loading={enviandoTodos}
                  icon="mail-outline"
                  label="Enviar para todos"
                  loadingLabel="Enviando..."
                  minWidth={170}
                />
              </View>
            </View>
          </View>

          <ScrollView horizontal showsHorizontalScrollIndicator={false}>
            <View style={{ minWidth: TABLE_MIN_WIDTH, width: "100%" }}>
              <View className="flex-row items-center border-b border-slate-300 px-4 py-2">
                <Text style={{ flex: 1.7, minWidth: 180 }} className="font-semibold color-slate-400 pr-3">
                  PARTICIPANTE
                </Text>
                <Text style={{ flex: 1.8, minWidth: 210 }} className="font-semibold color-slate-400 pr-3">
                  ATIVIDADE
                </Text>
                <Text style={{ flex: 2.2, minWidth: 260 }} className="font-semibold color-slate-400 pr-3">
                  E-MAIL
                </Text>
                <Text style={{ width: 140 }} className="font-semibold color-slate-400 pr-3">
                  STATUS
                </Text>
                <Text style={{ width: 280 }} className="font-semibold color-slate-400">
                  OPÇÕES
                </Text>
              </View>

              <View>
                {loadingEvento && (
                  <Text className="dark:color-white mt-3">Carregando evento...</Text>
                )}

                {!loadingEvento && !eventoId && (
                  <View className="mt-3">
                    <Text className="dark:color-white">
                      Nenhum evento selecionado. Selecione um evento para enviar certificados.
                    </Text>
                  </View>
                )}

                {!loadingEvento && !!eventoId && (
                  <View>
                    {loadingCertificados && (
                      <Text className="dark:color-white mt-3">Carregando certificados...</Text>
                    )}

                    {!loadingCertificados && listaVazia && (
                      <Text className="dark:color-white mt-3">Nenhum certificado encontrado.</Text>
                    )}

                    {!loadingCertificados && semResultadoBusca && (
                      <Text className="dark:color-white mt-3">Nenhum certificado encontrado para a busca.</Text>
                    )}

                    {!loadingCertificados && certificadosFiltrados.map((certificado) => (
                      <CertificadoLinha
                        key={certificado.cod_certificado}
                        certificado={certificado}
                        enviando={!!enviandoIds[certificado.cod_certificado]}
                        baixando={!!baixandoIds[certificado.cod_certificado]}
                        onSend={() => { void handleSendOne(certificado); }}
                        onDownload={() => { void handleDownloadOne(certificado); }}
                      />
                    ))}
                  </View>
                )}
              </View>
            </View>
          </ScrollView>
        </View>
      </Mainframe>
    </ScrollView>
  );
}
