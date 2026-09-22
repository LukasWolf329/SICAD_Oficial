import { logout } from '@/app/utils/logout';
import Ionicons from '@expo/vector-icons/build/Ionicons';
import AsyncStorage from "@react-native-async-storage/async-storage";
import { router, useGlobalSearchParams, useNavigation, usePathname } from 'expo-router';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  useWindowDimensions,
  Image,
  Linking,
  Modal,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View
} from 'react-native';
import * as DocumentPicker from "expo-document-picker";
import { useEventosModal } from './EventosModalContext';
import NiceAlert from '../NiceAlert/NiceAlert';

/* ------------------------------------------------------
   TIPAGENS
-------------------------------------------------------*/
interface Evento {
  evento_id: number;
  evento_nome: string;
  data_inicio: string;
  data_fim: string;
  status_usuario: string;
  total_participantes: number | null;
}

type NiceAlertVariant = "error" | "info" | "success";

type NiceAlertState = {
  visible: boolean;
  title: string;
  message: string;
  variant: NiceAlertVariant;
};


const API_GET_EVENTOS =
  "https://sicad.linceonline.com.br/controller/get_dropdown_eventos.php";

const API_IMPORTAR_JSON =
  "https://sicad.linceonline.com.br/controller/importar_eventos_json_arquivo.php";

const API_REMOVER_EVENTO =
  "https://sicad.linceonline.com.br/controller/apagar_evento.php";

function parseApiResponse(raw: unknown) {
  if (typeof raw !== "string") return raw;

  const texto = raw.trim();

  try {
    return JSON.parse(texto);
  } catch {
    const inicioJson = texto.indexOf("{");
    if (inicioJson >= 0) {
      try {
        return JSON.parse(texto.slice(inicioJson));
      } catch {
        return null;
      }
    }
    return null;
  }
}


/* ------------------------------------------------------
   NAVBAR
-------------------------------------------------------*/
export function NavBar() {
  const navigation = useNavigation();
  const pathname = usePathname();
  const params = useGlobalSearchParams<{ id?: string | string[] }>();
  const idNaRota = Array.isArray(params.id) ? params.id[0] : params.id;
  const rotaAtualRef = useRef({ pathname, id: idNaRota ?? "" });
  rotaAtualRef.current = { pathname, id: idNaRota ?? "" };

  const [nome, setNome] = useState("");
  const [evento, setEvento] = useState<Evento[]>([]);
  const { showEventos, openEventos, closeEventos } = useEventosModal();
  const [showPerfil, setShowPerfil] = useState(false);

  const [importandoJson, setImportandoJson] = useState(false);
  const [carregandoEventos, setCarregandoEventos] = useState(false);
  const [eventoParaRemover, setEventoParaRemover] = useState<Evento | null>(null);
  const [removendoId, setRemovendoId] = useState<number | null>(null);

  // O ref bloqueia o segundo clique imediatamente, antes do próximo render.
  const operacaoEmAndamento = useRef(false);
  const versaoCarregamento = useRef(0);
  const operacaoOcupada = importandoJson || removendoId !== null;

  const [niceAlert, setNiceAlert] = useState<NiceAlertState>({
    visible: false,
    title: "",
    message: "",
    variant: "info",
  });

  function mostrarNiceAlert(
    title: string,
    message: string,
    variant: NiceAlertVariant = "info"
  ) {
    setNiceAlert({
      visible: true,
      title,
      message,
      variant,
    });
  }

  function fecharNiceAlert() {
    setNiceAlert((prev) => ({
      ...prev,
      visible: false,
    }));
  }
  /* ---------------- Carregar nome do usuário ---------------- */
  async function carregarUsuario() {
    const nomeSalvo = await AsyncStorage.getItem("userName");
    if (nomeSalvo) setNome(nomeSalvo);
  }

  useEffect(() => {
    carregarUsuario();
    const unsubscribe = navigation.addListener("focus", carregarUsuario);
    return unsubscribe;
  }, [navigation]);

  /* ---------------- Carregar eventos do backend ---------------- */
  const carregarEvento = useCallback(async () => {
    const versao = ++versaoCarregamento.current;
    setCarregandoEventos(true);

    try {
      const userId = await AsyncStorage.getItem("userId");
      if (!userId) {
        if (versao === versaoCarregamento.current) setEvento([]);
        return;
      }

      const response = await fetch(API_GET_EVENTOS, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ userId: Number(userId) }),
      });
      const data: any = parseApiResponse(await response.text());
      if (!response.ok || !data || typeof data !== "object" || data.ok === false) {
        throw new Error(data?.erro || `Não foi possível listar os eventos (HTTP ${response.status}).`);
      }
      const lista = Array.isArray(data) ? data : data.eventos;
      if (!Array.isArray(lista)) throw new Error("A API não retornou uma lista de eventos válida.");

      // A mesma pessoa pode aparecer como participante e organizador no mesmo
      // evento. Mantém um único cartão e prioriza o vínculo de organizador.
      const porId = new Map<number, Evento>();
      for (const item of lista) {
        if (!item || typeof item !== "object") continue;
        const id = Number(item.evento_id ?? item.codigo ?? 0);
        if (!Number.isInteger(id) || id <= 0) continue;
        const total = item.total_participantes == null ? null : Number(item.total_participantes);
        const normalizado: Evento = {
          evento_id: id,
          evento_nome: String(item.evento_nome ?? item.nome ?? "Evento"),
          data_inicio: String(item.data_inicio ?? ""),
          data_fim: String(item.data_fim ?? ""),
          status_usuario: String(item.status_usuario ?? item.tipo_vinculo ?? "").trim().toLowerCase(),
          total_participantes: total !== null && Number.isFinite(total) ? total : null,
        };
        if (!porId.has(id) || normalizado.status_usuario === "organizador") {
          porId.set(id, normalizado);
        }
      }
      if (versao === versaoCarregamento.current) {
        setEvento(Array.from(porId.values()));
      }
    } catch (error: unknown) {
      console.error("Erro ao carregar eventos:", error);
      if (versao === versaoCarregamento.current) {
        // Mantém a lista anterior em uma falha temporária.
        setNiceAlert({
          visible: true,
          title: "Não foi possível atualizar os eventos",
          message: error instanceof Error ? error.message : "Tente atualizar a lista novamente.",
          variant: "error",
        });
      }
    } finally {
      if (versao === versaoCarregamento.current) setCarregandoEventos(false);
    }
  }, []);

  useEffect(() => {
    void carregarEvento();
  }, [carregarEvento]);

  useEffect(() => {
    if (showEventos) void carregarEvento();
    else if (!operacaoEmAndamento.current) setEventoParaRemover(null);
  }, [showEventos, carregarEvento]);

  /* ---------------- Remoção do evento somente desta conta ---------------- */
  function fecharModalEventos() {
    if (operacaoEmAndamento.current) return;
    if (eventoParaRemover) {
      setEventoParaRemover(null);
      return;
    }
    closeEventos();
  }

  function solicitarRemocao(ev: Evento) {
    if (operacaoEmAndamento.current) return;
    // O backend identifica a conta pelo token e só remove os próprios vínculos.
    fecharNiceAlert();
    setEventoParaRemover(ev);
  }

  async function atualizarSelecaoAposRemocao(
    eventoId: number,
    userId: string,
    restantes: Evento[]
  ) {
    const chaveUsuario = `lastEventoId:${userId}`;
    const valores = await AsyncStorage.multiGet([chaveUsuario, "lastEventoId"]);
    const salvo = valores[0][1] || valores[1][1] || "";
    const rota = rotaAtualRef.current;

    // O parâmetro id da home representa o evento. Em outras telas, id pode
    // representar uma atividade; por isso, nelas usamos o evento salvo.
    const homeAtual = rota.pathname.endsWith("/home/page");
    const selecionado = homeAtual && rota.id ? rota.id : salvo;
    const chavesRemover = valores
      .filter(([, valor]) => valor !== null && Number(valor) === eventoId)
      .map(([chave]) => chave);
    if (chavesRemover.length > 0) await AsyncStorage.multiRemove(chavesRemover);

    if (Number(selecionado) === eventoId) {
      const proximo = restantes[0];
      if (proximo) {
        await AsyncStorage.setItem(chaveUsuario, String(proximo.evento_id));
      }
      // Sem eventos restantes, não reutiliza o ID do evento removido desta conta.
      // A home deve tratar id vazio sem consultar atividades/certificados.
      router.replace({
        pathname: "/(tabs)/(painel)/home/page",
        params: { id: proximo ? String(proximo.evento_id) : "" },
      });
    }
  }

  async function confirmarRemocao() {
    if (!eventoParaRemover || operacaoEmAndamento.current) return;
    const alvo = eventoParaRemover;

    operacaoEmAndamento.current = true;
    setRemovendoId(alvo.evento_id);
    fecharNiceAlert();
    const controller = new AbortController();
    let timeout: ReturnType<typeof setTimeout> | undefined;
    let pedidoEnviado = false;
    let respostaLida = false;

    try {
      const credenciais = await AsyncStorage.multiGet(["userId", "userToken"]);
      const userId = credenciais[0][1];
      const token = credenciais[1][1]?.trim();
      if (!userId || !token) {
        throw new Error("Login não identificado. Entre novamente para remover o evento da sua conta.");
      }

      timeout = setTimeout(() => controller.abort(), 25000);
      pedidoEnviado = true;
      const response = await fetch(API_REMOVER_EVENTO, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
        // O usuário é identificado pelo token no servidor, não por este corpo.
        body: JSON.stringify({ eventoId: alvo.evento_id }),
        signal: controller.signal,
      });
      const data: any = parseApiResponse(await response.text());
      respostaLida = true;
      clearTimeout(timeout);

      if (!response.ok) {
        throw new Error(
          typeof data?.erro === "string"
            ? data.erro
            : `Não foi possível confirmar a remoção da sua conta (HTTP ${response.status}). Atualize a lista.`
        );
      }
      if (!data || data.ok !== true || Number(data.eventoId) !== alvo.evento_id) {
        throw new Error("Resposta inesperada do servidor. Atualize a lista antes de tentar remover novamente.");
      }

      if (data.escopo !== "usuario" || data.operacao !== "remover_evento_da_conta") {
        throw new Error("O servidor não confirmou uma remoção apenas desta conta. Verifique a versão do backend antes de tentar novamente.");
      }

      // Só remove da interface depois da confirmação do servidor.
      // Invalida requisições antigas para não recolocarem o evento na lista.
      ++versaoCarregamento.current;
      setCarregandoEventos(false);
      const restantes = evento.filter((ev) => ev.evento_id !== alvo.evento_id);
      setEvento((lista) => lista.filter((ev) => ev.evento_id !== alvo.evento_id));
      setEventoParaRemover(null);

      let mensagem = data.jaDesvinculado
        ? `O evento "${alvo.evento_nome}" já não estava vinculado à sua conta.`
        : `O evento "${alvo.evento_nome}" foi removido apenas da sua conta.`;
      try {
        await atualizarSelecaoAposRemocao(alvo.evento_id, userId, restantes);
      } catch (error) {
        // A desvinculação JÁ ocorreu. Uma falha local não deve ser apresentada
        // como uma falha na operação confirmada pelo servidor.
        console.error("Evento desvinculado, mas a seleção local não foi atualizada:", error);
        mensagem += " Não foi possível atualizar a seleção local. Selecione outro evento na lista.";
      }
      mostrarNiceAlert("Evento removido da sua conta", mensagem, "success");
    } catch (error: unknown) {
      const mensagem = pedidoEnviado && !respostaLida
        ? "Não foi possível confirmar a resposta do servidor. O evento pode já ter sido removido da sua conta. Cancele esta confirmação e atualize a lista antes de tentar novamente."
        : error instanceof Error ? error.message : "Não foi possível remover o evento da sua conta.";
      mostrarNiceAlert("Remoção não confirmada", mensagem, "error");
    } finally {
      if (timeout !== undefined) clearTimeout(timeout);
      operacaoEmAndamento.current = false;
      setRemovendoId(null);
    }
  }

  async function importarJsonPeloBotaoAdicionar() {
    if (operacaoEmAndamento.current) return;
    operacaoEmAndamento.current = true;

    try {
      setImportandoJson(true);

      const resultado = await DocumentPicker.getDocumentAsync({
        type: ["application/json", "text/json", "text/plain"],
        multiple: false,
        copyToCacheDirectory: true,
      });

      if (resultado.canceled) {
        return;
      }

      const arquivo = resultado.assets?.[0];

      if (!arquivo) {
        throw new Error("Nenhum arquivo foi selecionado.");
      }

      const nomeArquivo = arquivo.name || "eventos.json";

      if (!nomeArquivo.toLowerCase().endsWith(".json")) {
        throw new Error("Selecione um arquivo com extensão .json.");
      }

      const userId = await AsyncStorage.getItem("userId");

      if (!userId) {
        throw new Error("Usuário não identificado. Faça login novamente.");
      }

      const formData = new FormData();

      /*
        No Web, o DocumentPicker pode retornar um File em arquivo.file.
        No Android/iOS, normalmente usamos uri/name/type.
      */
      const arquivoWeb = (arquivo as any).file;

      if (arquivoWeb) {
        formData.append("arquivo_json", arquivoWeb, nomeArquivo);
      } else {
        formData.append("arquivo_json", {
          uri: arquivo.uri,
          name: nomeArquivo,
          type: arquivo.mimeType || "application/json",
        } as any);
      }

      formData.append("userId", String(userId));

      const response = await fetch(API_IMPORTAR_JSON, {
        method: "POST",
        body: formData,
      });

      const raw = await response.text();

      console.log("importar JSON status:", response.status);
      console.log("RAW importar JSON:", raw);

      const data: any = parseApiResponse(raw);

      if (!response.ok) {
        throw new Error(
          data?.erro ||
          data?.message ||
          raw ||
          `Erro HTTP ${response.status}`
        );
      }

      if (!data || data.ok !== true) {
        throw new Error(data?.erro || "O backend não retornou sucesso.");
      }

      await carregarEvento();

      const resultadoImportacao = data.resultado ?? {};

      mostrarNiceAlert(
        "Importação concluída",
        `Eventos: ${resultadoImportacao.total_eventos ?? 0}\n` +
        `Atividades: ${resultadoImportacao.total_atividades ?? 0}\n` +
        `Participantes vinculados: ${resultadoImportacao.total_participantes_vinculados ?? 0
        }`,
        "success"
      );
    } catch (error: any) {
      console.error("Erro ao importar JSON:", error);

      mostrarNiceAlert(
        "Erro ao importar JSON",
        error?.message || "Não foi possível importar o arquivo JSON.",
        "error"
      );
    } finally {
      setImportandoJson(false);
      operacaoEmAndamento.current = false;
    }
  }

  /* ---------------- Abrir evento com params ---------------- */
  async function abrirEvento(eventoId: number) {
    if (operacaoEmAndamento.current) return;
    closeEventos();

    const userId = await AsyncStorage.getItem("userId");
    const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
    await AsyncStorage.setItem(key, String(eventoId));

    router.replace({
      pathname: "/(tabs)/(painel)/home/page",
      params: { id: String(eventoId) },
    });
  }

  /* ------------------------------------------------------
       RENDER
  -------------------------------------------------------*/
  return (
    <View className="flex-row justify-between items-center px-4 bg-[#059212]">

      {/* LOGO */}
      <View className="p-2">
        <Image source={require("../../assets/images/logo-composta-branca.png")} />
      </View>

      {/* MENU PRINCIPAL */}
      <View className="flex-row items-center gap-6">

        {/* Botão Meus eventos */}
        <Pressable onPress={openEventos} className="flex-row items-center">
          <Text className="text-white text-base">Meus eventos </Text>
          <Ionicons name="caret-down-outline" size={20} color="white" />
        </Pressable>

        {/* Área do Organizador */}
        <Pressable className="flex-row items-center">
          <Text className="text-white text-base">Área do Organizador </Text>
          <Ionicons name="caret-down-outline" size={20} color="white" />
        </Pressable>

        {/* Perfil */}
        <View className="flex-row items-center gap-2">
          <Image
            source={require("../../assets/images/favicon.png")}
            style={{ width: 40, height: 40, borderRadius: 50 }}
          />
          <Pressable onPress={() => setShowPerfil(true)} className="flex-row items-center">
            <Text className="text-white text-base">{nome}</Text>
            <Ionicons name="caret-down-outline" size={20} color="white" />
          </Pressable>
        </View>
      </View>

      {/* ---------------- MODAL MEUS EVENTOS ---------------- */}
      <Modal
        visible={showEventos}
        animationType="fade"
        transparent
        onRequestClose={fecharModalEventos}
      >
        <View className="flex-1 bg-black/40 justify-center items-center">
          <View className="w-11/12 md:w-2/3 bg-white rounded-2xl shadow-xl max-h-[80%]">
            <View className="bg-[#2192FF] rounded-t-2xl flex-row justify-between items-center px-4 py-3">
              <Ionicons name={eventoParaRemover ? "trash-outline" : "browsers-outline"} size={22} color="#fff" />
              <Text className="text-white text-lg font-semibold">
                {eventoParaRemover ? "Remover da minha conta" : "Meus Eventos"}
              </Text>
              <View className="flex-row items-center gap-3">
                {!eventoParaRemover && (
                  <Pressable
                    onPress={() => void carregarEvento()}
                    disabled={operacaoOcupada || carregandoEventos}
                    accessibilityRole="button"
                    accessibilityLabel="Atualizar lista de eventos"
                    hitSlop={8}
                    style={{ opacity: operacaoOcupada || carregandoEventos ? 0.5 : 1 }}
                  >
                    <Ionicons name="refresh-outline" size={22} color="#fff" />
                  </Pressable>
                )}
                <Pressable
                  onPress={fecharModalEventos}
                  disabled={operacaoOcupada}
                  accessibilityRole="button"
                  accessibilityLabel={eventoParaRemover ? "Cancelar remoção" : "Fechar eventos"}
                  hitSlop={8}
                  style={{ opacity: operacaoOcupada ? 0.5 : 1 }}
                >
                  <Ionicons name="close-outline" size={24} color="#fff" />
                </Pressable>
              </View>
            </View>

            {/* Feedback dentro do modal, sem depender de alertas nativos no Web. */}
            {niceAlert.visible && (
              <View
                accessibilityRole="alert"
                className={`mx-4 mt-3 rounded-lg border p-3 ${
                  niceAlert.variant === "error" ? "bg-red-50 border-red-200"
                    : niceAlert.variant === "success" ? "bg-green-50 border-green-200"
                      : "bg-blue-50 border-blue-200"
                }`}
              >
                <View className="flex-row items-center justify-between">
                  <Text className="font-semibold text-gray-800 flex-1">{niceAlert.title}</Text>
                  <Pressable onPress={fecharNiceAlert} accessibilityRole="button" accessibilityLabel="Fechar aviso" hitSlop={8}>
                    <Ionicons name="close-outline" size={22} color="#555" />
                  </Pressable>
                </View>
                <Text className="text-gray-700 mt-1">{niceAlert.message}</Text>
              </View>
            )}

            {/* Usa o mesmo Modal: confirmação funciona também no Web. */}
            {eventoParaRemover ? (
              <ScrollView className="px-4 py-4" contentContainerStyle={{ paddingBottom: 20 }}>
                <Text className="text-lg font-semibold text-gray-800">Remover este evento apenas da sua conta?</Text>
                <Text className="mt-3 font-semibold text-gray-800">{eventoParaRemover.evento_nome}</Text>
                <Text className="mt-3 text-gray-700">
                  O evento será removido apenas da sua conta. O evento, as atividades e os
                  vínculos dos outros usuários serão mantidos.
                </Text>
                <Text className="mt-3 text-red-700 font-semibold">
                  Serão removidos seus vínculos de organizador e suas inscrições nas atividades
                  deste evento, inclusive os registros de presença dessas inscrições. Não é apenas
                  uma ocultação na lista.
                </Text>
                <Text className="mt-2 text-gray-600">
                  Os certificados já emitidos não serão apagados do banco por esta operação.
                  Para voltar a participar ou organizar, será necessário vincular sua conta novamente.
                </Text>
                <View className="flex-col md:flex-row md:justify-end gap-3 mt-6">
                  <Pressable
                    onPress={() => setEventoParaRemover(null)}
                    disabled={operacaoOcupada}
                    accessibilityRole="button"
                    className="rounded-lg border border-gray-300 px-4 py-3 items-center justify-center"
                    style={{ opacity: operacaoOcupada ? 0.5 : 1 }}
                  >
                    <Text className="text-gray-700 font-semibold">Cancelar</Text>
                  </Pressable>
                  <Pressable
                    onPress={() => void confirmarRemocao()}
                    disabled={operacaoOcupada}
                    accessibilityRole="button"
                    accessibilityLabel="Confirmar remoção do evento somente da minha conta"
                    accessibilityState={{ disabled: operacaoOcupada, busy: removendoId !== null }}
                    className="rounded-lg bg-red-600 px-4 py-3 flex-row items-center justify-center gap-2"
                    style={{ opacity: operacaoOcupada ? 0.6 : 1 }}
                  >
                    {removendoId !== null
                      ? <ActivityIndicator size="small" color="#fff" />
                      : <Ionicons name="trash-outline" size={18} color="#fff" />}
                    <Text className="text-white font-semibold">
                      {removendoId !== null ? "Removendo..." : "Sim, remover da minha conta"}
                    </Text>
                  </Pressable>
                </View>
              </ScrollView>
            ) : (
              <>
                {/* Filtro e busca originais */}
                <View className="flex-row items-center gap-2 mb-3 p-4">
                  <View className="border rounded-lg px-3 py-2 flex-row items-center gap-1">
                    <Text className="font-semibold">Todos</Text>
                    <Ionicons name="chevron-down-outline" size={22} />
                  </View>
                  <TextInput placeholder="Busque meus eventos..." className="flex-1 border rounded-lg px-3 py-2" />
                  <View className="border rounded-lg px-3 py-2 flex-row items-center">
                    <Ionicons name="search" size={22} color="#555" />
                  </View>
                </View>

                <ScrollView className="px-4" showsVerticalScrollIndicator={false}>
                  {carregandoEventos && (
                    <View className="py-3 flex-row justify-center items-center gap-2">
                      <ActivityIndicator size="small" color="#2192FF" />
                      <Text className="text-gray-600">Atualizando eventos...</Text>
                    </View>
                  )}
                  {!carregandoEventos && evento.length === 0 && (
                    <Text className="text-center text-gray-600 py-5">
                      Nenhum evento disponível. Use Adicionar para importar um evento.
                    </Text>
                  )}
                  {evento.map((ev) => (
                    <View key={ev.evento_id} className="flex-row items-center border rounded-xl mb-2 bg-gray-50">
                      {/* Abrir e remover são botões irmãos: remover não abre o evento. */}
                      <Pressable
                        onPress={() => void abrirEvento(ev.evento_id)}
                        disabled={operacaoOcupada}
                        accessibilityRole="button"
                        accessibilityLabel={`Abrir evento ${ev.evento_nome}`}
                        className="flex-row items-center flex-1 p-3"
                        style={{ opacity: operacaoOcupada ? 0.6 : 1 }}
                      >
                        <View className="w-16 h-10 bg-gray-300 rounded-md mr-3" />
                        <View className="flex-1">
                          <Text className="font-semibold text-gray-800">{ev.evento_nome}</Text>
                          <Text className="text-gray-600 text-sm">
                            {ev.status_usuario === "participante" && `Participante – ${ev.data_inicio} – Inscrito`}
                            {ev.status_usuario === "organizador" && `Organizador – ${ev.data_inicio} – ${ev.total_participantes ?? 0} participantes`}
                          </Text>
                        </View>
                      </Pressable>
                      {/* Disponível para os eventos listados; a API só remove vínculos da própria conta. */}
                      <Pressable
                        onPress={() => solicitarRemocao(ev)}
                        disabled={operacaoOcupada}
                        accessibilityRole="button"
                        accessibilityLabel={`Remover evento ${ev.evento_nome} somente da minha conta`}
                        className="p-3 mr-1"
                        style={{ opacity: operacaoOcupada ? 0.5 : 1 }}
                      >
                        <Ionicons name="trash-outline" size={24} color="#dc2626" />
                      </Pressable>
                    </View>
                  ))}
                  <Pressable
                    onPress={importarJsonPeloBotaoAdicionar}
                    disabled={operacaoOcupada}
                    className="border-t py-3 mt-2 flex-row justify-center items-center"
                    style={{ opacity: operacaoOcupada ? 0.6 : 1 }}
                  >
                    <Ionicons name={importandoJson ? "cloud-upload-outline" : "add-outline"} size={20} color="#2192FF" />
                    <Text className="text-[#2192FF] font-semibold">
                      {importandoJson ? " Importando JSON..." : " Adicionar"}
                    </Text>
                  </Pressable>
                </ScrollView>
              </>
            )}
          </View>
        </View>
      </Modal>

      {/* ---------------- MODAL PERFIL ---------------- */}
      <Modal visible={showPerfil} animationType="fade" transparent onRequestClose={() => setShowPerfil(false)}>
        <View className="flex-1 bg-black/40 justify-center items-center">
          <View className="w-2/12 bg-white rounded-2xl shadow-xl max-h-[80%]">

            <View className="bg-[#2192FF] rounded-t-2xl flex-row justify-between items-center px-4 py-3">
              <Ionicons name="person-circle-outline" size={22} color="#fff" />
              <Text className="text-white text-lg font-semibold">Perfil</Text>
              <Pressable onPress={() => setShowPerfil(false)}>
                <Ionicons name="close-outline" size={24} color="#fff" />
              </Pressable>
            </View>

            <Pressable className="p-3 mt-2 flex-row items-center">
              <Text className="font-semibold">Meu Perfil</Text>
            </Pressable>

            <Pressable className="p-3 mt-2 flex-row items-center">
              <Text className="font-semibold">Meus Eventos</Text>
            </Pressable>

            <Pressable className="p-3 mt-2 flex-row items-center">
              <Text className="font-semibold">Meus Materiais</Text>
            </Pressable>

            <Pressable onPress={logout} className="border-t p-3 mt-2 flex-row items-center rounded-b-2xl">
              <Ionicons name="log-out-outline" size={20} />
              <Text className="font-semibold ml-2">Sair</Text>
            </Pressable>

          </View>
        </View>
      </Modal>
      <NiceAlert
        visible={niceAlert.visible && !showEventos}
        title={niceAlert.title}
        message={niceAlert.message}
        variant={niceAlert.variant}
        onClose={fecharNiceAlert}
      />
    </View>
  );
}

/* ------------------------------------------------------
   SIDEBAR
-------------------------------------------------------*/
export function SideBar({ children }: { children?: React.ReactNode }) {
  const { width } = useWindowDimensions();
  const telaPequena = width < 768;
  const [montado, setMontado] = useState(false);
  const [isOpen, setIsOpen] = useState(true);

  // O HTML inicial é igual no servidor e na primeira renderização do cliente.
  // Só adapta a estrutura à largura da janela depois da montagem.
  const isSmall = montado && telaPequena;

  useEffect(() => {
    setMontado(true);
    setIsOpen(!telaPequena);
  }, [telaPequena]);

  return (
    <>
      {isSmall && (
        <Pressable
          onPress={() => setIsOpen((anterior) => !anterior)}
          className="absolute top-4 left-4 z-20 p-2 bg-[#059212] rounded"
        >
          <Ionicons name={isOpen ? "close" : "menu"} size={28} color="white" />
        </Pressable>
      )}

      {isOpen && (
        <View
          className={`h-full p-4 ${isSmall ? "absolute left-0 top-0 w-[70%] z-10 shadow-lg bg-white" : "w-[250px]"
            }`}
        >
          {children}
        </View>
      )}
    </>
  );
}

/* ------------------------------------------------------
   SIDEBAR CATEGORY
-------------------------------------------------------*/
type IoniconName = React.ComponentProps<typeof Ionicons>["name"];

interface SideBarItemProps {
  nome: string;
  icone: IoniconName;
  link: string;
}

interface SideBarCategoryProps {
  titulo: string;
  itens: SideBarItemProps[];
}

export function SideBarCategory({ titulo, itens }: SideBarCategoryProps) {
  return (
    <View>
      <Text className="text-2xl color-slate-600 dark:color-white font-bold mb-4">
        {titulo}
      </Text>

      {itens.map((item, idx) => (
        <Pressable
          key={idx}
          className="flex-row items-center mb-4 mx-4"
          onPress={() => router.push(item.link as any)}
        >
          <Ionicons name={item.icone} size={24} className="mr-2 dark:color-white" />
          <Text className="text-xl ml-2 dark:color-white">{item.nome}</Text>
        </Pressable>
      ))}
    </View>
  );
}

/* ------------------------------------------------------
   MAINFRAME
-------------------------------------------------------*/
export function Mainframe({
  children,
  name,
  link,
}: {
  children?: React.ReactNode;
  name: string;
  link?: string;
}) {
  return (
    <View className="bg-white dark:bg-[#242424] p-4 pb-20">
      <View className="flex-row m-8 items-center">
        <Image
          source={require("../../assets/images/user.png")}
          style={{ width: 80, height: 80 }}
          className="rounded-full"
        />

        <View>
          <Text className="text-2xl mx-4 font-semibold dark:color-[#e0e0e0]">
            {name}
          </Text>

          {link && (
            <Pressable onPress={() => Linking.openURL(`https://${link}`)}>
              <Text className="text-sky-500 mx-4 font-semibold">{link}</Text>
            </Pressable>
          )}
        </View>
      </View>

      {children}
    </View>
  );
}
